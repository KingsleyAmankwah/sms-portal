<?php
$pageTitle = 'Sender ID Management';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../components/header.php';

use SMSPortalExtensions\Authentication;

$csrfToken = ($_SERVER['REQUEST_METHOD'] === 'GET') ? Authentication::createToken() : ($_SESSION['csrf_token'] ?? '');

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Manage Sender IDs</h4>
                    <p class="card-category">Administer sender IDs via GiantSMS API</p>
                </div>
                <div class="card-body">
                    <!-- Action Button -->
                    <div class="mb-4">
                        <button type="button" class="btn btn-primary" id="request-sender-id-btn" data-toggle="modal" data-target="#requestSenderIdModal">
                            <i class="nc-icon nc-email-85"></i> Request Sender ID
                        </button>
                    </div>
                    <!-- Sender IDs Table -->
                    <div>
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Business Name</th>
                                    <th>Business Purpose</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="sender-ids-table-body">
                                <!-- Loading state -->
                                <tr id="sender-ids-loading" style="display: none;">
                                    <td colspan="6" class="text-center">
                                        <div class="spinner-border text-primary">
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Sender ID Modal -->
<div class="modal fade" id="requestSenderIdModal" tabindex="-1" aria-labelledby="requestSenderIdModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestSenderIdModalLabel">Request Sender ID</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">X</button>
            </div>
            <form id="request-sender-id-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="request">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="request-user-id">Select User *</label>
                                <select class="form-control" name="user_id" id="request-user-id" required>
                                    <option value="">Select a user</option>
                                    <!-- Users loaded via AJAX -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="request-business-name">Business Name *</label>
                                <input type="text" class="form-control" name="business_name" id="request-business-name" placeholder="Enter business name" maxlength="11" required>
                                <small class="form-text text-muted">Max 11 characters</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="request-business-purpose">Business Purpose *</label>
                                <textarea class="form-control" name="business_purpose" id="request-business-purpose" rows="4" minlength="20" maxlength="500" placeholder="Describe the purpose (min 20 characters)" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="request-sender-id-submit-btn">
                        <span class="spinner-border spinner-border-sm d-none" id="request-sender-id-spinner"></span>
                        <span id="request-sender-id-btn-text">Submit Request</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../components/footer.php'; ?>

<style>
    .btn-primary {
        background-color: #390546;
    }

    .btn-primary:hover {
        background-color: #4a0a6b !important;
    }

    .btn-secondary {
        background-color: #c73d6c;
    }

    .btn-secondary:hover {
        background-color: #a32b5a !important;
    }

    .badge-approved {
        background-color: #28a745;
    }

    .table th,
    .table td {
        vertical-align: middle;
    }

    .no-sender-ids-message {
        text-align: center;
        color: #6c757d;
        padding: 20px;
        font-size: 1.1em;
    }

    #sender-ids-loading {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .spinner-border {
        width: 3rem;
        height: 3rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Load sender IDs and users on page load
        loadSenderIds();
        loadUsersForRequest();

        // Handle form submission
        $('#request-sender-id-form').on('submit', function(e) {
            e.preventDefault();
            requestSenderId();
        });

        function loadSenderIds() {
            const tbody = $('#sender-ids-table-body');
            const loadingRow = $('#sender-ids-loading');

            loadingRow.show();
            tbody.find('tr').not(loadingRow).remove();

            $.ajax({
                url: '../controllers/process_sender_ids.php',
                type: 'POST',
                data: {
                    action: 'fetch',
                    csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    // Hide loading spinner
                    if (response.status_code === 'success') {
                        loadingRow.hide();
                        tbody.empty();
                        if (response.sender_ids && response.sender_ids.length > 0) {
                            response.sender_ids.forEach((sender, index) => {
                                tbody.append(`
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${sender.username || 'TekSed Inc.'} (${sender.email || 'admin@teksed.com'})</td>
                                    <td>${sender.business_name || ''}</td>
                                    <td>
                                        ${sender.business_purpose
                                            ? (sender.business_purpose.length > 60
                                        ? sender.business_purpose.slice(0, 60) + '...'
                                        : sender.business_purpose)
                                        : ''}
                                    </td>
                                    <td><span class="badge badge-approved">${sender.status ? ucFirst(sender.status) : 'Approved'}</span></td>
                                </tr>
                            `);
                            });
                        } else {
                            tbody.append(`
                            <tr>
                                <td colspan="6" class="no-sender-ids-message">
                                    No sender IDs found. Request a new one to get started.
                                </td>
                            </tr>
                        `);
                        }
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to load sender IDs',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    // Hide loading spinner
                    loadingRow.hide();

                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load sender IDs: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function loadUsersForRequest() {
            $.ajax({
                url: '../controllers/process_users.php',
                type: 'POST',
                data: {
                    action: 'fetch',
                    per_page: 100, // Load all users for simplicity
                    csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status_code === 'success') {
                        const select = $('#request-user-id');
                        select.empty().append('<option value="">Select a user</option>');
                        if (response.users && response.users.length > 0) {
                            response.users.forEach(user => {
                                if (!user.sender_id) { // Only show users without sender IDs
                                    select.append(`
                                    <option value="${user.id}">${user.username} (${user.email || ''})</option>
                                `);
                                }
                            });
                        } else {
                            select.append('<option value="">No users available</option>');
                        }
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to load users for sender ID request',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load users: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function requestSenderId() {
            const form = $('#request-sender-id-form');
            const btn = $('#request-sender-id-submit-btn');
            const spinner = $('#request-sender-id-spinner');
            const btnText = $('#request-sender-id-btn-text');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            btnText.text('Submitting...');

            $.ajax({
                url: '../controllers/process_sender_ids.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Submit Request');

                    if (response.status_code === 'success') {
                        form[0].reset();
                        $('#requestSenderIdModal').modal('hide');
                        loadSenderIds();
                        Swal.fire({
                            title: 'Success',
                            text: response.status,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to request sender ID',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Submit Request');

                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to request sender ID: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function ucFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
    });
</script>