@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><b>BNI</b></h3>
        </div>

        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('bni.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group mb-4">
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="exampleInputFile" name="fileExcel"
                            accept=".xlsx, .xls, .csv">
                        <label class="custom-file-label" for="exampleInputFile" id="fileLabel">Choose file</label>
                    </div>
                </div>
                <button class="btn btn-primary">Import Excel</button>
            </form>
            <br>
            <br>
            <div id="bni-table"></div>
            <br>
            <div class="form-group row">
                <label for="exportFileName" class="col-form-label col-sm-2">Nama File Export</label>
                <div class="col-sm-6">
                    <input type="text" id="exportFileName" class="form-control" required>
                </div>
            </div>
            <button id="download-xlsx" class="btn btn-success mb-2 float-right">Export to Excel</button>

            @if ($exported)
                <div class="modal fade" id="exportWarningModal" tabindex="-1" role="dialog"
                    aria-labelledby="exportWarningModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title" id="exportWarningModalLabel">Export Warning</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                Data sudah pernah di export, apakah ingin lanjut?
                            </div>
                            <div class="modal-footer">
                                <button id="confirm-export" type="button" class="btn btn-warning">Export</button>
                                <button id="cancel-export" type="button" class="btn btn-secondary"
                                    data-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Pass your PHP array to JS
        var tableData = @json($filteredArray ?? []);
        var title = @json($title ?? null)

        document.addEventListener('DOMContentLoaded', function() {
            var table = new Tabulator("#bni-table", {
                data: tableData,
                layout: "fitColumns",
                columns: [{
                        title: "Reference",
                        field: "reference",
                        editor: "input"
                    },
                    {
                        title: "Date",
                        field: "tanggal",
                        width: 100
                    },
                    {
                        title: "Label",
                        field: "keterangan",
                        width: 600,
                        formatter: "textarea"
                    },
                    {
                        title: "Partner",
                        field: "partner",
                        editor: "input"
                    },
                    {
                        title: "Amount",
                        field: "jumlah",
                        formatter: "money",
                        formatterParams: {
                            precision: 2,
                            thousand: ".",
                            decimal: ","
                        },
                        hozAlign: "right"
                    }
                ],
                cellEdited: function(cell) {}
            });

            function saveExportRecord(startDate, endDate, title) {
                console.log('Saving export:', {
                    start_date: startDate,
                    end_date: endDate,
                    title: title
                });
                return fetch("{{ route('bni.saveExport') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content') ||
                                "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({
                            start_date: startDate,
                            end_date: endDate,
                            title: title
                        })
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers.get('content-type'));

                        // Check if response is JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            return response.text().then(text => {
                                console.error('Non-JSON response:', text);
                                throw new Error(
                                    'Server returned non-JSON response. Check console for details.');
                            });
                        }

                        return response.json().then(data => ({
                            status: response.status,
                            data: data
                        }));
                    })
                    .then(result => {
                        console.log('Result:', result);
                        if (result.status !== 200 || result.data.status === 'error') {
                            throw new Error(result.data.message || 'Unknown error occurred');
                        }
                        return result.data;
                    })
                    .catch(error => {
                        console.error('Full error:', error);
                        alert('Error saving export record: ' + error.message);
                        throw error;
                    });
            }

            // Export to Excel
            document.getElementById("download-xlsx").addEventListener("click", function(e) {
                var exportFileName = document.getElementById("exportFileName").value.trim();
                if (!exportFileName) {
                    alert("Nama File Export wajib diisi!");
                    document.getElementById("exportFileName").focus();
                    e.preventDefault();
                    return false;
                }
                @if ($exported)
                    e.preventDefault();
                    $('#exportWarningModal').modal('show');
                @else
                    saveExportRecord(@json($startDate), @json($endDate),
                        exportFileName);
                    table.download("xlsx", exportFileName + "_" + @json($startDate) + "_" +
                        @json($endDate) + ".xlsx", {
                            sheetName: "BNI Data"
                        });
                @endif
            });

            // If user confirms export anyway
            var confirmBtn = document.getElementById("confirm-export");
            if (confirmBtn) {
                confirmBtn.addEventListener("click", function() {
                    var exportFileName = document.getElementById("exportFileName").value.trim();
                    if (!exportFileName) {
                        alert("Nama File Export wajib diisi!");
                        document.getElementById("exportFileName").focus();
                        return false;
                    }
                    saveExportRecord(@json($startDate), @json($endDate),
                        exportFileName);
                    table.download("xlsx", exportFileName + "_" + @json($startDate) + "_" +
                        @json($endDate) + ".xlsx", {
                            sheetName: "BNI Data"
                        });
                    $('#exportWarningModal').modal('hide');
                });
            }
            // Cancel button automatically closes modal via data-dismiss="modal"
        });
    </script>
@endsection
