@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><b>Bank Sumsel Babel</b></h3>
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
            <form action="{{ route('sumselbabel.store') }}" method="POST" enctype="multipart/form-data">
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
            <div id="sumselbabel-table"></div>
            <br>
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


<script>
    // Pass your PHP array to JS
    var tableData = @json($filteredArray ?? []);
    var title = @json($title ?? null)

    document.addEventListener('DOMContentLoaded', function() {
        var table = new Tabulator("#sumselbabel-table", {
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
            fetch("{{ route('sumselbabel.saveExport') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        start_date: startDate,
                        end_date: endDate,
                        title: title
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // Optionally show a success message
                });
        }

        // Export to Excel
        document.getElementById("download-xlsx").addEventListener("click", function(e) {
            @if ($exported)
                e.preventDefault();
                $('#exportWarningModal').modal('show');
            @else
                // Save to DB and export
                saveExportRecord(@json($startDate), @json($endDate), title);
                table.download("xlsx", title + "_" + @json($startDate) + "_" +
                    @json($endDate) + ".xlsx", {
                        sheetName: "Sumsel Babel Data"
                    });
            @endif
        });

        // If user confirms export anyway
        var confirmExportBtn = document.getElementById("confirm-export");
        if (confirmExportBtn) {
            confirmExportBtn.addEventListener("click", function() {
                saveExportRecord(@json($startDate), @json($endDate), title);
                table.download("xlsx", title + "_" + @json($startDate) + "_" +
                    @json($endDate) + ".xlsx", {
                        sheetName: "Sumsel Babel Data"
                    });
                $('#exportWarningModal').modal('hide');
            });
        }

        // Cancel button automatically closes modal via data-dismiss="modal"
    });
</script>
