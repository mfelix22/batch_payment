@extends('layouts.app')

@section('styles')
    <!-- Add DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
    <!-- Add Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
    <style>
        .existing-transaction {
            background-color: #ffe6e6 !important;
        }

        .existing-transaction td {
            color: #dc3545;
            text-decoration: line-through;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-pdf mr-2"></i>PDF Batch Payment Import</h3>
            </div>
            <div class="card-body">
                <!-- Upload Form -->
                <form id="uploadForm" enctype="multipart/form-data">
                    @csrf
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="pdfFile" name="pdf_file"
                                        accept=".pdf">
                                    <label class="custom-file-label" for="pdfFile">Choose PDF file</label>
                                </div>
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-upload mr-2"></i>Upload
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Header Information -->
                <div id="headerInfo" class="alert alert-info d-none">
                    <h5><i class="icon fas fa-info"></i> Document Information</h5>
                    <div id="headerContent"></div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table id="dataTable" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Trans.Date</th>
                                <th>Trans.Amount</th>
                                <th>Acc. No.</th>
                                <th>Bank Name</th>
                                <th>BI Code</th>
                                <th>Bank Branch</th>
                                <th>Remark 1</th>
                                <th>Remark 2</th>
                                <th>Jenis</th>
                                <th>Receiver Cust Type</th>
                                <th>Receiver Cust Residence</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button id="exportBtn" class="btn btn-success" disabled>
                        <i class="fas fa-file-export mr-2"></i>Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- Add required JS dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <!-- Add DataTables Buttons dependencies -->
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>

    <script>
        const API_BASE_URL = 'https://sdp.hartonomotor-group.com/batch_payment';

        $(document).ready(function() {
            let tableData = null;
            let bankAccount = '';
            let headerDate = '';
            let branchBatch = '';

            let table = $('#dataTable').DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 25,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                // "dom": 'Bfrtip', // Add buttons to the DOM
                "columns": [{
                        "data": 0
                    }, // Name
                    {
                        "data": 1
                    }, // Trans.Date
                    {
                        "data": 2, // Trans.Amount
                        "render": function(data) {
                            return `<span class="text-right d-block">${data}</span>`;
                        }
                    },
                    {
                        "data": 3,
                        "render": function(data, type, row, meta) {
                            if (type === 'display' && data && data.toString().includes('(VA)')) {
                                const base = data.toString().replace('(VA)', '').trim();
                                return `<span class="va-base d-none">${base}</span>
                                        ${base}<input type="text"
                                            class="va-suffix-input"
                                            maxlength="4"
                                            pattern="[0-9]{4}"
                                            placeholder="4 digits"
                                            style="width:60px;border:1px solid #ccc;border-radius:3px;padding:1px 4px;"
                                            title="Enter 4-digit VA suffix"> (VA)`;
                            }
                            return data;
                        }
                    }, // Acc. No.
                    {
                        "data": 4
                    }, // Bank Name
                    {
                        "data": 5
                    }, // BI Code
                    {
                        "data": 6
                    }, // Bank Branch
                    {
                        "data": 7, // Remark 1
                        "render": function(data, type, row, meta) {
                            if (row.exists) {
                                return `<span title="Transaction already exists">${data}</span>`;
                            }
                            return data;
                        }
                    },
                    {
                        "data": 8, // Remark 2
                        "render": function(data, type, row, meta) {
                            // Clean whitespace and newlines from display
                            const cleanedData = data ? data.replace(/[\r\n\s]+/g, '') : '';
                            return cleanedData;
                        }
                    },
                    {
                        "data": 9
                    }, // Jenis
                    {
                        "data": 10
                    }, // Receiver Cust Type
                    {
                        "data": 11
                    } // Receiver Cust Residence
                ],
                "createdRow": function(row, data, dataIndex) {
                    if (data.invalid) {
                        $(row).addClass('existing-transaction');
                        $(row).attr('title', 'Invalid BI Code - will not be exported');
                    } else if (data.exists) {
                        $(row).addClass('existing-transaction');
                        $(row).attr('title', 'Transaction already exists in database');
                    }
                }
            });

            $('#uploadForm').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                // Show loading state
                let submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin mr-2"></i>Processing...');

                $.ajax({
                    url: `${API_BASE_URL}/payment/upload`,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Store the data for export
                        tableData = response.data;
                        bankAccount = response.header.issuing_account;
                        headerDate = response.header.date;
                        branchBatch = response.header.branch_batch;

                        // Update header information
                        $('#headerInfo').html(`
        <h5><i class="icon fas fa-info"></i> Document Information</h5>
        <div class="row">
            <div class="col-md-4">
                <strong>Branch/Batch:</strong> ${response.header.branch_batch}
            </div>
            <div class="col-md-4">
                <strong>Date:</strong> ${response.header.date}
            </div>
            <div class="col-md-4">
                <strong>Account:</strong> ${response.header.issuing_account}
            </div>
        </div>
        <div class="mt-2">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i>
                Rows in red indicate transactions that already exist in the database or have invalid BI Code and will be skipped during export.
            </small>
        </div>
    `).removeClass('d-none');

                        // Clear and reload table
                        table.clear().rows.add(response.data).draw();

                        // Check for invalid rows (bad BI Code)
                        const invalidRows = response.data.filter(row => row.invalid);
                        const hasNewTransactions = response.data.some(row => !row.exists && !row
                            .invalid);

                        // Disable export button if there are invalid rows
                        if (invalidRows.length > 0) {
                            $('#exportBtn').prop('disabled', true)
                                .attr('title',
                                    `Cannot export: ${invalidRows.length} row(s) with invalid BI Code`
                                );
                            toastr.warning(
                                `${invalidRows.length} row(s) have invalid BI Code and cannot be exported`,
                                'Warning');
                        } else {
                            $('#exportBtn').prop('disabled', !hasNewTransactions)
                                .attr('title', '');
                        }

                        toastr.success('PDF processed successfully', 'Success');
                    },
                    error: function(xhr) {
                        toastr.error('Error processing PDF: ' + (xhr.responseJSON?.error ||
                            'Unknown error'), 'Error');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false)
                            .html('<i class="fas fa-upload mr-2"></i>Upload');
                    }
                });
            });

            // Handle file input change
            $(document).on('change', '.custom-file-input', function() {
                let fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });

            // Replace the export button handler
            $('#exportBtn').on('click', function() {
                // Validate and collect VA suffix inputs before exporting
                let vaError = false;
                $('#dataTable tbody tr').each(function() {
                    const $input = $(this).find('.va-suffix-input');
                    if ($input.length) {
                        const val = $input.val().trim();
                        if (!/^[0-9]{4}$/.test(val)) {
                            $input.css('border-color', 'red');
                            vaError = true;
                        } else {
                            $input.css('border-color', '#ccc');
                        }
                    }
                });
                if (vaError) {
                    toastr.warning('Please fill in all 4-digit VA suffixes before exporting.',
                        'VA Input Required');
                    return;
                }

                // Apply VA suffixes into tableData
                const rowNodes = table.rows().nodes();
                table.rows().every(function(rowIdx) {
                    const $row = $(rowNodes[rowIdx]);
                    const $input = $row.find('.va-suffix-input');
                    if ($input.length) {
                        const suffix = $input.val().trim();
                        const base = $row.find('.va-base').text().trim();
                        tableData[rowIdx][3] = base + suffix + ' (VA)';
                    }
                });

                const $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Exporting...');

                $.ajax({
                    url: `${API_BASE_URL}/payment/export`,
                    type: 'POST',
                    data: JSON.stringify({
                        rows: tableData,
                        issuing_account: bankAccount,
                        header_date: headerDate,
                        branch_batch: branchBatch
                    }),
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.data.length === 0) {
                            toastr.warning('No new records to export', 'Warning');
                            return;
                        }

                        // Show processing results
                        toastr.info(response.message, 'Processing Results');

                        // Generate timestamp for filename in Asia/Jakarta timezone
                        const now = new Date();
                        const jakartaTime = new Date(now.toLocaleString('en-US', {
                            timeZone: 'Asia/Jakarta'
                        }));

                        // Previous timestamp generation commented out
                        const timestamp = jakartaTime.getFullYear() +
                            String(jakartaTime.getMonth() + 1).padStart(2, '0') +
                            String(jakartaTime.getDate()).padStart(2, '0') +
                            String(jakartaTime.getHours()).padStart(2, '0') +
                            String(jakartaTime.getMinutes()).padStart(2, '0') +
                            String(jakartaTime.getSeconds()).padStart(2, '0');

                        // Alternative simpler timestamp without time
                        // const timestamp = jakartaTime.getFullYear() +
                        //     String(jakartaTime.getMonth() + 1).padStart(2, '0') +
                        //     String(jakartaTime.getDate()).padStart(2, '0');

                        if (response.fileType === 'csv') {
                            // Handle Mandiri CSV format
                            let csvContent = '';

                            // Convert header date from YYYY-MM-DD to YYYYMMDD format
                            let formattedDate = '-'; // Default fallback
                            if (headerDate) {
                                try {
                                    // Handle YYYY-MM-DD format (from PDF header)
                                    if (headerDate.includes('-')) {
                                        const dateParts = headerDate.split('-');
                                        if (dateParts.length === 3) {
                                            formattedDate = dateParts[0] + dateParts[1] +
                                                dateParts[2];
                                            console.log('Parsed YYYY-MM-DD format:', headerDate,
                                                '→', formattedDate);
                                        }
                                    }
                                    // Handle DD/MM/YYYY format (fallback)
                                    else if (headerDate.includes('/')) {
                                        const dateParts = headerDate.split('/');
                                        if (dateParts.length === 3) {
                                            formattedDate = dateParts[2] +
                                                String(dateParts[1]).padStart(2, '0') +
                                                String(dateParts[0]).padStart(2, '0');
                                            console.log('Parsed DD/MM/YYYY format:', headerDate,
                                                '→', formattedDate);
                                        }
                                    }
                                } catch (e) {
                                    console.error('Date parsing error:', e, 'headerDate:',
                                        headerDate);
                                }
                            } else {
                                console.warn('headerDate is empty');
                            }

                            // First row - header information
                            const firstRow = [
                                'P',
                                formattedDate,
                                response.issuing_account.replace(/\D/g, ''),
                                response.data.length,
                                response.total_amount
                            ].join(',');
                            csvContent += firstRow + '\r\n';

                            // Data rows - each complete transaction on one line
                            response.data.forEach(row => {
                                // Clean up company name - remove line breaks and extra spaces
                                const cleanCompanyName = row[0].replace(/\s+/g, ' ')
                                    .trim();

                                const rowData = [
                                    row[3], // Account number 
                                    cleanCompanyName, // Company name (cleaned)
                                    '', '', '', // 3 blank columns
                                    'IDR',
                                    row[2], // Amount
                                    row[7], // Remark1
                                    row[8], // Remark2
                                    'IBU',
                                    row[5].padStart(7, '0'), // BI Code
                                    'MANDIRI',
                                    '', '', '', '', // 4 blanks
                                    'N',
                                    '', '', '', '', // 4 blanks
                                    'Y'
                                ];

                                // Add 16 blank columns between Y and OUR
                                for (let i = 0; i < 16; i++) {
                                    rowData.push('');
                                }

                                // Add final fields
                                rowData.push('OUR', '2', 'E', '', '', '');

                                csvContent += rowData.join(',') + '\r\n';
                            });

                            // Create and trigger download
                            const blob = new Blob([csvContent], {
                                type: 'text/csv;charset=utf-8;'
                            });
                            const link = document.createElement("a");
                            const url = URL.createObjectURL(blob);
                            link.setAttribute("href", url);
                            link.setAttribute("download", `mandiri_export_${timestamp}.csv`);
                            link.style.visibility = 'hidden';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        } else {
                            // XLSX export logic
                            let ws = XLSX.utils.json_to_sheet(response.data.map(row => {
                                // Convert transaction date from DD/MM/YYYY to MM/DD/YYYY for Excel display
                                let transDate = row[1];
                                if (transDate && transDate.includes('/')) {
                                    const dateParts = transDate.split('/');
                                    if (dateParts.length === 3) {
                                        // Swap month and day: DD/MM/YYYY -> MM/DD/YYYY
                                        transDate = dateParts[1] + '/' + dateParts[
                                            0] + '/' + dateParts[2];
                                    }
                                }

                                return {
                                    'Name': row[0],
                                    'Trans.Date': transDate,
                                    'Trans.Amount': row[2],
                                    'Acc. No.': row[3],
                                    'Bank Name': row[4],
                                    'BI Code': row[5],
                                    'Bank Branch': row[6],
                                    'Remark 1': row[7],
                                    'Remark 2': row[8],
                                    'Jenis': row[9],
                                    'Receiver Cust Type': row[10],
                                    'Receiver Cust Residence': row[11]
                                };
                            }));

                            let wb = XLSX.utils.book_new();
                            XLSX.utils.book_append_sheet(wb, ws, "Payments");
                            XLSX.writeFile(wb, `payment_export_${timestamp}.xlsx`);
                        }

                        toastr.success('Data exported successfully', 'Success');
                    },
                    error: function(xhr) {
                        console.error('Export error:', xhr);
                        toastr.error('Export failed: ' + (xhr.responseJSON?.error ||
                            'Unknown error'), 'Error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(
                            '<i class="fas fa-file-export mr-2"></i>Export Data');
                    }
                });
            });
        });
    </script>
@endsection
