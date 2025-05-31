<?php
$pageTitle = 'Contact List';
require_once __DIR__ . '/../vendor/autoload.php';
include_once '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;

// Generate CSRF token
$csrf_token = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? Authentication::createToken()
    : ($_SESSION['csrf_token'] ?? '');

// Fetch groups for edit modal dropdown
$conn = MySQLDatabase::createConnection();
$groups = [];
if ($conn) {
    $result = MySQLDatabase::sqlSelect($conn, 'SELECT name FROM groups WHERE user_id = ?', 'i', $_SESSION['USER_ID']);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row['name'];
        }
        $result->free_result();
    }
    $conn->close();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Contact List</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="d-flex gap-2 w-50">
                            <div class="form-group w-50">
                                <input type="text" class="form-control" id="search-input" placeholder="Search by name or phone number">
                            </div>
                            <div class="form-group">
                                <select class="form-control" id="group-filter">
                                    <option value="">All Groups</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g); ?>">
                                            <?php echo htmlspecialchars($g); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="items-per-page" class="mr-2">Items per page:</label>
                            <select class="form-control d-inline-block w-auto" id="items-per-page">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-container">
                        <div id="loading-indicator" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading contacts...</p>
                        </div>
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Sn.</th>
                                    <th>Name</th>
                                    <th>Phone Number</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="contacts-table-body">
                                <!-- Contacts will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <div id="pagination-info"></div>
                        <nav>
                            <ul class="pagination" id="pagination-controls"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1" role="dialog" aria-labelledby="editContactModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editContactModalLabel">Edit Contact</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form id="edit-contact-form">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="contact_id" id="edit-contact-id">
                    <div class="form-group">
                        <label for="edit-name">Name</label>
                        <input type="text" class="form-control" name="name" id="edit-name" placeholder="Enter name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-phone_number">Phone Number (e.g., +233123456789)</label>
                        <input type="text" class="form-control" name="phone_number" id="edit-phone_number" placeholder="Enter phone number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-email">Email (optional)</label>
                        <input type="email" class="form-control" name="email" id="edit-email" placeholder="Enter email">
                    </div>
                    <div class="form-group">
                        <label for="edit-group">Group</label>
                        <select class="form-control" name="group" id="edit-group" required>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-company">Company (optional)</label>
                        <input type="text" class="form-control" name="company" id="edit-company" placeholder="Enter company">
                    </div>
                    <div class="form-group">
                        <label for="edit-notes">Notes (optional)</label>
                        <textarea class="form-control" name="notes" id="edit-notes" rows="4" placeholder="Enter notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="save-contact-btn">
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

    .gap-2 {
        gap: 0.5rem;
    }

    #group-filter {
        min-width: 150px;
    }



    .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }

    .table th,
    .table td {
        vertical-align: middle;
    }

    .action-btn {
        margin: 0 5px;
    }

    #search-input {
        max-width: 300px;
    }

    .pagination .page-link {
        cursor: pointer;
    }

    .no-contacts-message {
        text-align: center;
        color: #6c757d;
        padding: 20px;
        font-size: 1.1em;
    }

    .page-item.active .page-link {
        background-color: #390546;
        border-color: #390546;
    }

    .page-link {
        color: #390546;
    }

    .page-link:hover {
        color: #4a0a6b;
    }

    .page-item.disabled .page-link:hover {
        color: #6c757d;
        background-color: transparent;
        border-color: transparent;
    }

    .page-item.active .page-link:hover {
        background-color: #4a0a6b;
        border-color: #4a0a6b;
    }

    .page-item.active .page-link:focus {
        box-shadow: 0 0 0 0.2rem rgba(57, 4, 70, 0.5);
    }

    .page-item .page-link {
        border-radius: 0.25rem;
    }

    #loading-indicator {
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

    .table-container {
        position: relative;
        min-height: 200px;
    }

    .table-fade {
        transition: opacity 0.3s ease-in-out;
    }

    .table-fade.loading {
        opacity: 0.5;
    }
</style>

<script>
    // Debounce function to limit search requests
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(event) {
            const later = () => {
                clearTimeout(timeout);
                func(event);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        let currentPage = 1;
        let itemsPerPage = 10;
        let searchQuery = '';
        let selectedGroup = '';
        const loadingIndicator = document.getElementById('loading-indicator');
        const contactsTable = document.querySelector('.table');

        function showLoading() {
            loadingIndicator.classList.remove('d-none');
            contactsTable.classList.add('table-fade', 'loading');
        }

        function hideLoading() {
            loadingIndicator.classList.add('d-none');
            contactsTable.classList.remove('table-fade', 'loading');
        }

        // Load contacts on page load
        loadContacts();

        // Handle search input
        $('#search-input').on('input', debounce(function(event) {
            const input = event.target;
            searchQuery = input && typeof input.value === 'string' ? input.value.trim() : '';
            currentPage = 1; // Reset to first page on search
            loadContacts();
        }, 300));

        // Handle items per page change
        $('#items-per-page').on('change', function() {
            itemsPerPage = parseInt($(this).val());
            currentPage = 1; // Reset to first page
            loadContacts();
        });

        // Handle edit form submission
        $('#edit-contact-form').on('submit', function(e) {
            e.preventDefault();
            updateContact();
        });

        // Handle pagination clicks (using event delegation)
        $('#pagination-controls').on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page === 'prev') {
                if (currentPage > 1) currentPage--;
            } else if (page === 'next') {
                currentPage++;
            } else {
                currentPage = page;
            }
            loadContacts();
        });

        // Add group filter handler
        $('#group-filter').on('change', function() {
            selectedGroup = $(this).val();
            currentPage = 1;
            loadContacts();
        });

        function loadContacts() {
            showLoading();
            $.ajax({
                url: '../controllers/process_contacts.php',
                type: 'POST',
                data: {
                    action: 'fetch',
                    page: currentPage,
                    items_per_page: itemsPerPage,
                    search: searchQuery,
                    group: selectedGroup,
                    csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.status_code === 'success') {
                        const tbody = $('#contacts-table-body');
                        tbody.empty();
                        if (response.contacts && response.contacts.length > 0) {
                            response.contacts.forEach((contact, index) => {
                                const rowNumber = (response.current_page - 1) * response.items_per_page + index + 1;
                                tbody.append(`
                                <tr data-contact-id="${contact.id}">
                                    <td>${rowNumber}</td>
                                    <td>${contact.name || ''}</td>
                                    <td>${contact.phone_number || ''}</td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-primary action-btn edit-btn"
                                                data-id="${contact.id}"
                                                data-name="${contact.name || ''}"
                                                data-phone="${contact.phone_number || ''}"
                                                data-email="${contact.email || ''}"
                                                data-group="${contact.group || ''}"
                                                data-company="${contact.company || ''}"
                                                data-notes="${contact.notes || ''}">
                                            Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger action-btn delete-btn" data-id="${contact.id}">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            `);
                            });
                        } else {
                            // Display message based on search query
                            const message = searchQuery ?
                                'No matching contacts found for your search.' :
                                'No contacts found. Add contacts to get started.';
                            tbody.append(`
                            <tr>
                                <td colspan="7" class="no-contacts-message">${message}</td>
                            </tr>
                        `);
                        }

                        // Update pagination controls
                        updatePagination(response.total_contacts, response.items_per_page, response.current_page);

                        // Bind edit and delete button events
                        $('.edit-btn').on('click', function() {
                            const btn = $(this);
                            $('#edit-contact-id').val(btn.data('id'));
                            $('#edit-name').val(btn.data('name'));
                            $('#edit-phone_number').val(btn.data('phone'));
                            $('#edit-email').val(btn.data('email'));
                            $('#edit-group').val(btn.data('group'));
                            $('#edit-company').val(btn.data('company'));
                            $('#edit-notes').val(btn.data('notes'));
                            $('#editContactModal').modal('show');
                        });

                        $('.delete-btn').on('click', function() {
                            const contactId = $(this).data('id');
                            Swal.fire({
                                title: 'Are you sure?',
                                text: 'This contact will be permanently deleted.',
                                icon: 'warning',
                                confirmButtonColor: '#c73d6c',
                                cancelButtonColor: '#4a0a6b',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, delete it!',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    deleteContact(contactId);
                                }
                            });
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to load contacts',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to load contacts: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function updatePagination(totalContacts, itemsPerPage, currentPage) {
            const totalPages = Math.ceil(totalContacts / itemsPerPage);
            const pagination = $('#pagination-controls');
            const info = $('#pagination-info');
            pagination.empty();

            // Update pagination info
            const start = totalContacts > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0;
            const end = Math.min(currentPage * itemsPerPage, totalContacts);
            info.text(`Showing ${start} to ${end} of ${totalContacts} contacts`);

            // Add Previous button
            pagination.append(`
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" data-page="prev">Previous</a>
            </li>
        `);

            // Add page numbers (limit to 5 for simplicity)
            const maxPagesToShow = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
            let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
            if (endPage - startPage + 1 < maxPagesToShow) {
                startPage = Math.max(1, endPage - maxPagesToShow + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                pagination.append(`
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" data-page="${i}">${i}</a>
                </li>
            `);
            }

            // Add Next button
            pagination.append(`
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" data-page="next">Next</a>
            </li>
        `);
        }

        function deleteContact(contactId) {
            $.ajax({
                url: '../controllers/process_contacts.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    contact_id: contactId,
                    csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status_code === 'success') {
                        loadContacts(); // Refresh table
                        Swal.fire({
                            title: 'Success',
                            text: response.status,
                            icon: 'success',
                            confirmButtonText: 'OK',
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to delete contact',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to delete contact: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function updateContact() {
            const form = $('#edit-contact-form');
            const btn = $('#save-contact-btn');
            const spinner = $('#save-spinner');
            const btnText = $('#save-btn-text');

            btn.attr('disabled', true);
            spinner.removeClass('d-none');
            btnText.text('Saving...');

            $.ajax({
                url: '../controllers/process_contacts.php',
                type: 'POST',
                data: form.serialize() + '&action=update',
                dataType: 'json',
                success: function(response) {
                    btn.attr('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Save Changes');

                    if (response.status_code === 'success') {
                        $('#editContactModal').modal('hide');
                        loadContacts(); // Refresh table
                        Swal.fire({
                            title: 'Success',
                            text: response.status,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.status || 'Failed to update contact',
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
                        text: 'Failed to update contact: ' + (xhr.responseJSON?.status || 'Server error'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
</script>