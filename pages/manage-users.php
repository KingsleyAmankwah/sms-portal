<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../core/admin-check.php';
include_once '../components/header.php';
verifyAdminAccess();

use SMSPortalExtensions\Authentication;

$csrfToken = ($_SERVER['REQUEST_METHOD'] === 'GET') ? Authentication::createToken() : ($_SESSION['csrf_token'] ?? '');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Manage Users</h4>
                    <p class="card-category">Administer system users</p>
                </div>
                <div class="card-body">
                    <!-- Action Buttons -->
                    <div class="mb-4">
                        <button type="button" class="btn btn-primary" id="add-user-btn" data-target="#addUserModal" data-toggle="modal">
                            <i class="nc-icon nc-simple-add"></i> Add User
                        </button>
                    </div>
                    <!-- Filter Form -->
                    <div class="mb-4">
                        <form id="filter-form" class="form-inline">
                            <div class="form-group mr-3 mb-2">
                                <input type="text" class="form-control" name="search" id="search-filter" placeholder="Search users...">
                            </div>
                            <div class="form-group mr-3 mb-2">
                                <select class="form-control" name="status" id="status-filter">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">In active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="form-group mr-3 mb-2">
                                <select class="form-control" name="role" id="role-filter">
                                    <option value="">All Roles</option>
                                    <option value="admin">Admin</option>
                                    <option value="client">Client</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-info mb-2">Filter</button>
                            <button type="button" class="btn btn-secondary mb-2" id="reset-filter">Reset</button>
                        </form>
                    </div>
                    <!-- Users Table -->
                    <div class="">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <!-- Users loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <nav aria-label="Page navigation" id="pagination" class="d-none">
                        <ul class="pagination justify-content-center"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">X</button>
            </div>
            <form id="add-user-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="add-username">Username *</label>
                                <input type="text" class="form-control" name="username" id="add-username" placeholder="Enter username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="add-email">Email Address *</label>
                                <input type="email" class="form-control" name="email" id="add-email" placeholder="Enter email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="add-phone">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone" id="add-phone" placeholder="Enter phone number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="add-role">User Role *</label>
                                <select class="form-control" name="role" id="add-role" required>
                                    <option value="client">Client</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="add-user-submit-btn">
                        <span class="spinner-border spinner-border-sm d-none" id="add-user-spinner"></span>
                        <span id="add-user-btn-text">Add User</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">X</button>
            </div>
            <form id="edit-user-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit-user-id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-username">Username *</label>
                                <input type="text" class="form-control" name="username" id="edit-username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-email">Email Address *</label>
                                <input type="email" class="form-control" name="email" id="edit-email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-phone">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone" id="edit-phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-role">User Role *</label>
                                <select class="form-control" name="role" id="edit-role" required>
                                    <option value="client">Client</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="save-user-btn">
                        <span class="spinner-border spinner-border-sm d-none" id="save-spinner"></span>
                        <span id="save-btn-text">Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include '../components/footer.php'; ?>

<style>
    .btn-primary {
        background-color: #390546;
    }

    .btn-primary:hover {
        background-color: #4a0a6b !important;
    }

    .btn-secondary,
    .btn-danger {
        background-color: #c73d6c;
    }

    .btn-secondary:hover,
    .btn-danger:hover {
        background-color: #a32b5a !important;
    }

    .badge-primary {
        background-color: #390546;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-danger {
        background-color: #dc3545;
    }

    .table th,
    .table td {
        vertical-align: middle;
    }

    .action-btn {
        margin: 0 5px;
    }

    .no-users-message {
        text-align: center;
        color: #6c757d;
        padding: 20px;
        font-size: 1.1em;
    }

    .kebab-menu {
        border: none;
        background: transparent;
        color: #6c757d;
        padding: 0.25rem 0.5rem;
    }

    .kebab-menu:hover {
        background-color: #f8f9fa;
        color: #495057;
    }

    .dropdown-menu {
        min-width: 160px;
    }

    .dropdown-item {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .dropdown-item i {
        width: 16px;
        text-align: center;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Load users on page load
        loadUsers();

        // Handle form submissions
        $('#add-user-form').on('submit', function(e) {
            e.preventDefault();
            addUser();
        });

        $('#edit-user-form').on('submit', function(e) {
            e.preventDefault();
            updateUser();
        });

        $('#request-sender-id-form').on('submit', function(e) {
            e.preventDefault();
            requestSenderId();
        });

        $('#filter-form').on('submit', function(e) {
            e.preventDefault();
            loadUsers();
        });

        $('#reset-filter').on('click', () => {
            $('#search-filter').val('');
            $('#status-filter').val('');
            $('#role-filter').val('');
            loadUsers();
        });

        // Load users for sender ID request modal
        $('#requestSenderIdModal').on('show.bs.modal', function() {
            loadUsersForSenderId();
        });


        function loadUsers(page = 1) {
            $.ajax({
                url: '../controllers/process_users.php',
                type: 'POST',
                data: {
                    action: 'fetch',
                    page: page,
                    per_page: 10,
                    search: $('#search-filter').val(),
                    status: $('#status-filter').val(),
                    role: $('#role-filter').val(),
                    csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status_code === 'success') {
                        const tbody = $('#users-table-body');
                        tbody.empty();
                        if (response.users && response.users.length > 0) {
                            response.users.forEach((user, index) => {
                                tbody.append(`
                            <tr data-user-id="${user.id}">
                                <td>${index + 1}</td>
                                <td>${user.username || ''}</td>
                                <td>${user.email || ''}</td>
                                <td>${user.phone || ''}</td>
                                <td><span class="badge badge-${user.type === 'admin' ? 'primary' : 'info'}">${user.type ? ucFirst(user.type) : ''}</span></td>
                                <td><span class="badge badge-${
                                    user.status === 'active' ? 'success' :
                                    user.status === 'suspended' ? 'danger' : 'warning'
                                }">${user.status ? ucFirst(user.status) : ''}</span></td>
                                <td>${user.last_login ? formatDateTime(user.last_login) : 'Never'}</td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light dropdown-toggle" type="button"
                                                data-toggle="dropdown" aria-expanded="false"
                                                data-id="${user.id}"
                                                data-username="${user.username || ''}"
                                                data-email="${user.email || ''}"
                                                data-phone="${user.phone || ''}"
                                                data-role="${user.type || ''}"
                                                data-business-name="${user.business_name || ''}"
                                                data-sender-id="${user.sender_id || ''}"
                                                data-status="${user.status}">
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item edit-action" href="#"
                                                   data-id="${user.id}"
                                                   data-username="${user.username || ''}"
                                                   data-email="${user.email || ''}"
                                                   data-phone="${user.phone || ''}"
                                                   data-role="${user.type || ''}"
                                                   data-business-name="${user.business_name || ''}"
                                                   data-sender-id="${user.sender_id || ''}">
                                                    <i class="nc-icon nc-ruler-pencil mr-2"></i>Edit User
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item reset-password-action" href="#"
                                                   data-id="${user.id}">
                                                    <i class="nc-icon nc-key-25 mr-2"></i>Reset Password
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item status-action" href="#"
                                                   data-id="${user.id}"
                                                   data-status="${user.status}">
                                                   <i class="nc-icon ${user.status === 'active' ? 'nc-simple-remove' : 'nc-check-2'} mr-2"></i>
                                                    ${user.status === 'active' ? 'Suspend' : 'Activate'} User
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger delete-action" href="#"
                                                   data-id="${user.id}">
                                                    <i class="nc-icon nc-simple-remove mr-2"></i>Delete User
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        `);
                            });

                            updatePagination(response.pagination);
                        } else {
                            tbody.append(`
                        <tr>
                            <td colspan="9" class="no-users-message">
                                No users found. Add a user to get started.
                            </td>
                        </tr>
                    `);
                            $('#pagination').addClass('d-none');
                        }

                        // Bind action handlers
                        bindActionHandlers();
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to load users',
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

        function bindActionHandlers() {
            // CLICK EVENTS FOR DROPDOWN ACTIONS

            // Edit user click event
            $(document).off('click', '.edit-action').on('click', '.edit-action', function(e) {
                e.preventDefault();
                const link = $(this);
                $('#edit-user-id').val(link.data('id'));
                $('#edit-username').val(link.data('username'));
                $('#edit-email').val(link.data('email'));
                $('#edit-phone').val(link.data('phone'));
                $('#edit-role').val(link.data('role'));
                $('#edit-business-name').val(link.data('business-name'));
                $('#edit-sender-id').val(link.data('sender-id'));
                $('#editUserModal').modal('show');
            });

            // Reset password click event
            $(document).off('click', '.reset-password-action').on('click', '.reset-password-action', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');
                Swal.fire({
                    title: 'Reset Password',
                    text: 'Are you sure you want to reset this user\'s password? A new temporary password will be generated.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, reset it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#c73d6c',
                    cancelButtonColor: '#4a0a6b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        resetPassword(userId);
                    }
                });
            });

            // Status change click event
            $(document).off('click', '.status-action').on('click', '.status-action', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');
                const currentStatus = $(this).data('status');
                const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
                Swal.fire({
                    title: `${newStatus === 'active' ? 'Activate' : 'Suspend'} User`,
                    text: `Are you sure you want to ${newStatus === 'active' ? 'activate' : 'suspend'} this user?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#c73d6c',
                    cancelButtonColor: '#4a0a6b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        updateStatus(userId, newStatus);
                    }
                });
            });

            // Delete user click event
            $(document).off('click', '.delete-action').on('click', '.delete-action', function(e) {
                e.preventDefault();
                const userId = $(this).data('id');
                Swal.fire({
                    title: 'Delete User',
                    html: `
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone!</p>
                <p>Are you sure you want to delete this user account?</p>
            `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#c73d6c',
                    cancelButtonColor: '#4a0a6b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteUser(userId);
                    }
                });
            });
        }


        function loadUsersForSenderId() {
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
                                select.append(`
                                <option value="${user.id}">${user.username} (${user.email})</option>
                            `);
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

        function addUser() {
            const form = $('#add-user-form');
            const btn = $('#add-user-submit-btn');
            const spinner = $('#add-user-spinner');
            const btnText = $('#add-user-btn-text');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            btnText.text('Adding...');

            $.ajax({
                url: '../controllers/process_users.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Add User');

                    if (response.status_code === 'success') {
                        form[0].reset();
                        $('#addUserModal').modal('hide');
                        loadUsers();
                        Swal.fire({
                            title: 'Success',
                            html: `
                            <p>${response.status}</p>
                            <p>Temporary password: <strong>${response.temp_password}</strong></p>
                        `,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to add user',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Add User');

                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to add user: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function updateUser() {
            const form = $('#edit-user-form');
            const btn = $('#save-user-btn');
            const spinner = $('#save-spinner');
            const btnText = $('#save-btn-text');

            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            btnText.text('Saving...');

            $.ajax({
                url: '../controllers/process_users.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Save Changes');

                    if (response.status_code === 'success') {
                        $('#editUserModal').modal('hide');
                        loadUsers();
                        Swal.fire({
                            title: 'Success',
                            text: response.status,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to update user',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Save Changes');

                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to update user: ' + (xhr.responseJSON?.status || 'Server error'),
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
                url: '../controllers/process_users.php',
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
                        Swal.fire({
                            title: 'Success',
                            text: response.status,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to submit sender ID request',
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
                        text: 'Failed to submit sender ID request: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function resetPassword(userId) {
            $.ajax({
                url: '../controllers/process_users.php',
                type: 'POST',
                data: {
                    action: 'reset_password',
                    user_id: userId,
                    csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status_code === 'success') {
                        Swal.fire({
                            title: 'Success',
                            html: `
                            <p>${response.status}</p>
                            <p>Temporary password: <strong>${response.temp_password}</strong></p>
                        `,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to reset password',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to reset password: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function updateStatus(userId, newStatus) {
            $.ajax({
                url: '../controllers/process_users.php',
                type: 'POST',
                data: {
                    action: 'update_status',
                    user_id: userId,
                    new_status: newStatus,
                    csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status_code === 'success') {
                        loadUsers();
                        Swal.fire({
                            title: 'Success',
                            text: response.status,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to update status',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to update status: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function deleteUser(userId) {
            $.ajax({
                url: '../controllers/process_users.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    user_id: userId,
                    csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status_code === 'success') {
                        loadUsers();
                        Swal.fire({
                            title: 'Success',
                            text: response.status,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to delete user',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to delete user: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function updatePagination(pagination) {
            const ul = $('#pagination ul');
            ul.empty();

            if (pagination.total_pages > 1) {
                $('#pagination').removeClass('d-none');
                ul.append(`
                    <li class="page-item ${pagination.current_page <= 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${pagination.current_page - 1}">Previous</a>
                    </li>
                `);

                for (let i = 1; i <= pagination.total_pages; i++) {
                    ul.append(`
                        <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `);
                }

                ul.append(`
                    <li class="page-item ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${pagination.current_page + 1}">Next</a>
                    </li>
                `);

                $('.page-link').on('click', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    if (page && !$(this).parent().hasClass('disabled')) {
                        loadUsers(page);
                    }
                });
            } else {
                $('#pagination').addClass('d-none');
            }
        }

        function ucFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            if (isNaN(date)) return '';
            const day = date.toLocaleString('en-US', {
                weekday: 'short'
            });
            const month = date.toLocaleString('en-US', {
                month: 'short'
            });
            const dayNum = date.getDate();
            const year = date.getFullYear();
            const time = date.toLocaleString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            return `${day}, ${month} ${dayNum}, ${year} at ${time}`;
        }
    });
</script>