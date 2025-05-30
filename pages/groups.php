<?php
$pageTitle = 'Groups Management';
require_once __DIR__ . '/../vendor/autoload.php';
include_once '../components/header.php';

use SMSPortalExtensions\Authentication;

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
                            <div class="form-group mr-2">
                                <input type="text" class="form-control" name="group_name" id="add-group-name" placeholder="Enter group name" required>
                            </div>
                            <button type="submit" class="btn btn-primary" id="add-group-btn">
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

    .text-warning {
        color: #c73d6c !important;
    }

    .btn-secondary:hover,
    .btn-danger:hover {
        background-color: #a32b5a !important;
    }

    .table th,
    .table td {
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

    .edit-btn.disabled,
    .delete-btn.disabled {
        cursor: not-allowed;
        opacity: 0.65;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Load groups on page load
        loadGroups();

        // Handle add group form submission
        $('#add-group-form').on('submit', function(e) {
            e.preventDefault();
            addGroup();
        });

        // Handle edit group form submission
        $('#edit-group-form').on('submit', function(e) {
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
                success: function(response) {
                    if (response.status_code === 'success') {
                        const tbody = $('#groups-table-body');
                        tbody.empty();
                        if (response.groups && response.groups.length > 0) {
                            response.groups.forEach(group => {
                                tbody.append(`
                                <tr data-group-id="${group.id}">
                                    <td>${group.name || ''}</td>
                                    <td>${group.contact_count || 0}</td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary action-btn edit-btn"
                                                data-id="${group.id}"
                                                data-name="${group.name || ''}"
                                        >
                                            Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger action-btn delete-btn"
                                                data-id="${group.id}"
                                                data-name="${group.name || ''}"
                                              >
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
                        $('.edit-btn:not(.disabled)').on('click', function() {
                            const btn = $(this);
                            $('#edit-group-id').val(btn.data('id'));
                            $('#edit-group-name').val(btn.data('name'));
                            $('#editGroupModal').modal('show');
                        });

                        $('.delete-btn:not(.disabled)').on('click', function() {
                            const groupId = $(this).data('id');
                            const groupName = $(this).data('name');
                            const contactCount = parseInt($(this).closest('tr').find('td:eq(1)').text()) || 0;
                            deleteGroup(groupId, contactCount);
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
                error: function(xhr) {
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
                success: function(response) {
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
                error: function(xhr) {
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
                success: function(response) {
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
                error: function(xhr) {
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

        function deleteGroup(groupId, contactCount) {
            if (contactCount === 0) {
                // Direct delete if no contacts
                Swal.fire({
                    title: 'Delete Empty Group',
                    text: 'Are you sure you want to delete this empty group?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#c73d6c',
                    cancelButtonColor: '#4a0a6b',
                }).then((result) => {
                    if (result.isConfirmed) {
                        proceedWithDelete(groupId, 'delete');
                    }
                });
            } else {
                $.ajax({
                    url: '../controllers/process_groups.php',
                    type: 'POST',
                    data: {
                        action: 'fetch',
                        exclude_id: groupId,
                        csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
                    },
                    success: function(response) {
                        const otherGroups = response.groups ? response.groups.filter(g => g.id != groupId) : [];

                        if (otherGroups.length === 0) {
                            // No other groups available
                            Swal.fire({
                                title: 'Delete Group',
                                html: `
                            <div class="">
                                <p>This group contains ${contactCount} contact${contactCount > 1 ? 's' : ''}.</p>
                                <p class="text-warning">There are no other groups available to move contacts to.</p>
                                <p class="text-left">You can either:</p>
                                <ul class="text-left">
                                    <li>Create another group first</li>
                                    <li>Delete the contacts with this group</li>
                                </ul>
                            </div>
                            <div class="mt-3">
                                <select id="delete-action" class="form-control" required>
                                    <option value="">Select an option</option>
                                    <option value="delete">Delete contacts with group</option>
                                    <option value="create">Create new group first</option>
                                </select>
                            </div>
                        `,
                                showCancelButton: true,
                                confirmButtonText: 'Proceed',
                                confirmButtonColor: '#c73d6c',
                                cancelButtonColor: '#4a0a6b',
                                preConfirm: () => {
                                    const action = document.getElementById('delete-action').value;
                                    if (!action) {
                                        Swal.showValidationMessage('Please select an option');
                                        return false;
                                    }
                                    return {
                                        action
                                    };
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    if (result.value.action === 'create') {
                                        // Redirect to create group section
                                        $('#add-group-name').focus();
                                        Swal.fire({
                                            title: 'Create New Group',
                                            text: 'Please create a new group first, then try deleting this group again.',
                                            icon: 'info',
                                            confirmButtonText: 'OK'
                                        });
                                    } else {
                                        proceedWithDelete(groupId, 'delete');
                                    }
                                }
                            })
                        } else {
                            showDeleteOptions(groupId, contactCount, otherGroups);
                        }
                    }
                })

            }
        }

        function proceedWithDelete(groupId, action = 'delete', targetGroupId = null) {
            $.ajax({
                url: '../controllers/process_groups.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    group_id: groupId,
                    delete_action: action,
                    target_group_id: targetGroupId,
                    csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
                },
                dataType: 'json',
                success: function(response) {
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
                error: function(xhr) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to delete group: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function showDeleteOptions(groupId, contactCount, availableGroups) {
            Swal.fire({
                title: 'Delete Group',
                html: `
            <p>This group contains ${contactCount} contact${contactCount > 1 ? 's' : ''}. What would you like to do with them?</p>
            <select id="delete-action" class="form-control mt-2" required>
                <option value="">Select option below</option>
                <option value="move">Move contacts to another group</option>
                <option value="delete">Delete contacts with group</option>
            </select>
            <select id="target-group" class="form-control mt-2 d-none">
                <option value="">Select target group</option>
                ${availableGroups.map(group =>
                    `<option value="${group.id}">${group.name}</option>`
                ).join('')}
            </select>
        `,
                showCancelButton: true,
                confirmButtonText: 'Proceed',
                confirmButtonColor: '#c73d6c',
                cancelButtonColor: '#4a0a6b',
                didOpen: () => {
                    const targetGroup = document.getElementById('target-group');
                    const deleteAction = document.getElementById('delete-action');

                    // Show/hide target group dropdown based on selection
                    deleteAction.addEventListener('change', function() {
                        targetGroup.classList.toggle('d-none', this.value !== 'move');
                    });
                },
                preConfirm: () => {
                    const action = document.getElementById('delete-action').value;
                    const targetGroupId = document.getElementById('target-group').value;
                    if (!action) {
                        Swal.showValidationMessage('Please select an option');
                        return false;
                    }
                    if (action === 'move') {
                        if (!targetGroupId) {
                            Swal.showValidationMessage('Please select a target group');
                            return false;
                        }
                    }

                    return {
                        action,
                        targetGroupId: action === 'move' ? targetGroupId : null
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    proceedWithDelete(groupId, result.value.action, result.value.targetGroupId);
                }
            });
        }
    });
</script>