<?php
require_once __DIR__ . '/../vendor/autoload.php';
include '../components/header.php';

use SMSPortalExtensions\Authentication;

// Ensure customLog is defined
if (!function_exists('customLog')) {
    define('CUSTOM_LOG', 'C:\xampp\htdocs\dashboard-master\debug.log');
    function customLog($message) {
        file_put_contents(CUSTOM_LOG, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    }
}

// Generate CSRF token
$csrf_token = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? Authentication::createToken()
    : ($_SESSION['csrf_token'] ?? '');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Manage Groups</h4>
                </div>
                <div class="card-body">
                    <!-- Add Group Form -->
                    <div class="mb-4">
                        <form id="add-group-form" class="form-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div class="form-group mb-2 mr-2">
                                <input type="text" class="form-control" name="group_name" id="add-group-name" placeholder="Enter group name" required>
                            </div>
                            <button type="submit" class="btn btn-primary mb-2" id="add-group-btn">
                                <span class="spinner-border spinner-border-sm d-none" id="add-spinner"></span>
                                <span id="add-btn-text">Add Group</span>
                            </button>
                        </form>
                    </div>
                    <!-- Groups Table -->
                    <div class="table">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Group Name</th>
                                    <th>Contacts</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="groups-table-body">
                                <!-- Groups will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1" role="dialog" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupModalLabel">Edit Group</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form id="edit-group-form">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="group_id" id="edit-group-id">
                    <div class="form-group">
                        <label for="edit-group-name">Group Name</label>
                        <input type="text" class="form-control" name="group_name" id="edit-group-name" placeholder="Enter group name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="save-group-btn">
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
.table-responsive {
    min-height: 200px;
}
.table th, .table td {
    vertical-align: middle;
}
.action-btn {
    margin: 0 5px;
}
.no-groups-message {
    text-align: center;
    color: #6c757d;
    padding: 20px;
    font-size: 1.1em;
}
.edit-btn.disabled, .delete-btn.disabled {
    cursor: not-allowed;
    opacity: 0.65;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Load groups on page load
    loadGroups();

    // Handle add group form submission
    $('#add-group-form').on('submit', function (e) {
        e.preventDefault();
        addGroup();
    });

    // Handle edit group form submission
    $('#edit-group-form').on('submit', function (e) {
        e.preventDefault();
        updateGroup();
    });

    function loadGroups() {
        $.ajax({
            url: '../controllers/process_groups.php',
            type: 'POST',
            data: {
                action: 'fetch',
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function (response) {
                if (response.status_code === 'success') {
                    const tbody = $('#groups-table-body');
                    tbody.empty();
                    if (response.groups && response.groups.length > 0) {
                        response.groups.forEach(group => {
                            const isAllGroup = group.name.toLowerCase() === 'all';
                            tbody.append(`
                                <tr data-group-id="${group.id}">
                                    <td>${group.name || ''}</td>
                                    <td>${group.contact_count || 0}</td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary action-btn edit-btn ${isAllGroup ? 'disabled' : ''}" 
                                                data-id="${group.id}" 
                                                data-name="${group.name || ''}"
                                                ${isAllGroup ? 'disabled title="Cannot edit default group"' : ''}>
                                            Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger action-btn delete-btn ${isAllGroup ? 'disabled' : ''}" 
                                                data-id="${group.id}" 
                                                data-name="${group.name || ''}"
                                                ${isAllGroup ? 'disabled title="Cannot delete default group"' : ''}>
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                    } else {
                        tbody.append(`
                            <tr>
                                <td colspan="3" class="no-groups-message">No groups found. Add a group to get started.</td>
                            </tr>
                        `);
                    }

                    // Bind edit and delete button events
                    $('.edit-btn:not(.disabled)').on('click', function () {
                        const btn = $(this);
                        $('#edit-group-id').val(btn.data('id'));
                        $('#edit-group-name').val(btn.data('name'));
                        $('#editGroupModal').modal('show');
                    });

                    $('.delete-btn:not(.disabled)').on('click', function () {
                        const groupId = $(this).data('id');
                        const groupName = $(this).data('name');
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `Deleting "${groupName}" will reassign its contacts to the "All" group.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                deleteGroup(groupId);
                            }
                        });
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.status || 'Failed to load groups',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function (xhr) {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to load groups: ' + (xhr.responseJSON?.status || 'Server error'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    function addGroup() {
        const form = $('#add-group-form');
        const btn = $('#add-group-btn');
        const spinner = $('#add-spinner');
        const btnText = $('#add-btn-text');

        btn.attr('disabled', true);
        spinner.removeClass('d-none');
        btnText.text('Adding...');

        $.ajax({
            url: '../controllers/process_groups.php',
            type: 'POST',
            data: form.serialize() + '&action=create',
            dataType: 'json',
            success: function (response) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Add Group');

                if (response.status_code === 'success') {
                    form[0].reset();
                    loadGroups();
                    Swal.fire({
                        title: 'Success',
                        text: response.status,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.status || 'Failed to add group',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function (xhr) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Add Group');

                Swal.fire({
                    title: 'Error',
                    text: 'Failed to add group: ' + (xhr.responseJSON?.status || 'Server error'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    function updateGroup() {
        const form = $('#edit-group-form');
        const btn = $('#save-group-btn');
        const spinner = $('#save-spinner');
        const btnText = $('#save-btn-text');

        btn.attr('disabled', true);
        spinner.removeClass('d-none');
        btnText.text('Saving...');

        $.ajax({
            url: '../controllers/process_groups.php',
            type: 'POST',
            data: form.serialize() + '&action=update',
            dataType: 'json',
            success: function (response) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Save Changes');

                if (response.status_code === 'success') {
                    $('#editGroupModal').modal('hide');
                    loadGroups();
                    Swal.fire({
                        title: 'Success',
                        text: response.status,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.status || 'Failed to update group',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function (xhr) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Save Changes');

                Swal.fire({
                    title: 'Error',
                    text: 'Failed to update group: ' + (xhr.responseJSON?.status || 'Server error'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    function deleteGroup(groupId) {
        $.ajax({
            url: '../controllers/process_groups.php',
            type: 'POST',
            data: {
                action: 'delete',
                group_id: groupId,
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function (response) {
                if (response.status_code === 'success') {
                    loadGroups();
                    Swal.fire({
                        title: 'Success',
                        text: response.status,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.status || 'Failed to delete group',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function (xhr) {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to delete group: ' + (xhr.responseJSON?.status || 'Server error'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }
});
</script>