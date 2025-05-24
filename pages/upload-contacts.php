<?php
include '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;

// Generate CSRF token
$csrf_token = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? Authentication::createToken()
    : ($_SESSION['csrf_token'] ?? '');

// Fetch groups for dropdown
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

// Persist form data on error
$individual_data = [
    'name' => $_POST['name'] ?? '',
    'phone_number' => $_POST['phone_number'] ?? '',
    'email' => $_POST['email'] ?? '',
    'group' => $_POST['group'] ?? 'All',
    'company' => $_POST['company'] ?? '',
    'notes' => $_POST['notes'] ?? ''
];
?>
<div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card p-4">
                <div class="d-flex justify-content-between mb-4">
                    <h4 class="card-title">Upload Contacts</h4>
                </div>
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#individual">Individual Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#bulk">Bulk Upload</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- Individual Upload Form -->
                    <div class="tab-pane fade show active" id="individual">
                        <form action="../controllers/process_contacts_upload.php" method="POST" autocomplete="off" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="upload_type" value="individual">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" class="form-control" name="name" id="name" placeholder="Enter name" required value="<?php echo htmlspecialchars($individual_data['name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone_number">Phone Number (e.g., +233123456789)</label>
                                <input type="text" class="form-control" name="phone_number" id="phone_number" placeholder="Enter phone number" required value="<?php echo htmlspecialchars($individual_data['phone_number']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email (optional)</label>
                                <input type="email" class="form-control" name="email" id="email" placeholder="Enter email" value="<?php echo htmlspecialchars($individual_data['email']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="group">Group</label>
                                <select class="form-control" name="group" id="group">
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $g === $individual_data['group'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="company">Company (optional)</label>
                                <input type="text" class="form-control" name="company" id="company" placeholder="Enter company" value="<?php echo htmlspecialchars($individual_data['company']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes (optional)</label>
                                <textarea class="form-control" name="notes" id="notes" rows="4" placeholder="Enter notes"><?php echo htmlspecialchars($individual_data['notes']); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" id="add-btn">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="spinner-add"></span>
                                <span id="btn-text-add">Add Contact</span>
                            </button>
                        </form>
                    </div>
                    <!-- Bulk Upload Form -->
                    <div class="tab-pane fade" id="bulk">
                        <form action="../controllers/process_contacts_upload.php" method="POST" enctype="multipart/form-data" autocomplete="off" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="upload_type" value="bulk">
                            <div class="form-group">
                                <label for="group_bulk">Group (applied if not specified in file)</label>
                                <select class="form-control" name="group" id="group_bulk">
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $g === 'All' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Upload File (CSV, XLS, XLSX)</label>
                                <div class="upload-area text-center p-4 border rounded">
                                    <p><a href="#" onclick="$('#file').click()">Browse</a> device for your file</p>
                                    <i class="nc-icon nc-cloud-upload-94" style="font-size: 2em;"></i>
                                    <input type="file" class="form-control-file" name="file" id="file" accept=".csv,.xls,.xlsx" style="display:none;" required onchange="$('#file-name').text(this.files[0].name)">
                                    <p id="file-name" class="mt-2"></p>
                                    <small class="form-text text-muted">Supports CSV and Excel files</small>
                                </div>
                            </div>
                            <p><strong>File Format Requirements:</strong></p>
                            <ul>
                                <li>File must be in CSV or Excel (.xlsx, .xls) format</li>
                                <li>Must contain at least two columns: Name and Phone Number</li>
                                <li>Phone numbers should include country code (e.g., +233)</li>
                                <li>Optional columns: Email, Group, Company, Notes</li>
                            </ul>
                            <p><a href="../assets/files/sample_contacts.xlsx" download>Download Sample Excel File</a></p>
                            <button type="submit" class="btn btn-primary w-100" id="import-btn">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="spinner-import"></span>
                                <span id="btn-text-import">Import Contacts</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.upload-area {
    background: #f8f9fa;
    border: 2px dashed #ccc;
    transition: all 0.3s ease;
}
.upload-area:hover {
    background: #e9ecef;
    border-color: #aaa;
}
.upload-area p {
    margin: 0;
    font-size: 1.1em;
}
.upload-area small {
    display: block;
    margin-top: 0.5em;
}
</style>

<?php
include '../components/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Prioritize URL tab parameter, then session, then default
    const urlParams = new URLSearchParams(window.location.search);
    let activeTab = urlParams.get('tab') || '<?php echo isset($_SESSION['active_tab']) ? $_SESSION['active_tab'] : 'individual'; ?>';
    <?php if (isset($_SESSION['active_tab'])) unset($_SESSION['active_tab']); ?>

    // Ensure valid tab
    if (!['individual', 'bulk'].includes(activeTab)) {
        activeTab = 'individual';
    }

    // Activate tab
    $(`.nav-link[href="#${activeTab}"]`).tab('show');

    // Update URL when tab is changed
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const tabId = $(e.target).attr('href').substring(1);
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('tab', tabId);
        history.replaceState(null, '', newUrl);
    });
});

$('form').on('submit', function (e) {
    e.preventDefault(); // Prevent page refresh

    const isIndividual = $(this).find('input[name="upload_type"]').val() === 'individual';
    const btnId = isIndividual ? '#add-btn' : '#import-btn';
    const spinnerId = isIndividual ? '#spinner-add' : '#spinner-import';
    const textId = isIndividual ? '#btn-text-add' : '#btn-text-import';
    const form = this;

    $(btnId).attr('disabled', true);
    $(spinnerId).removeClass('d-none');
    $(textId).text('Processing...');

    // Prepare form data for AJAX
    const formData = new FormData(form);

    $.ajax({
        url: '../controllers/process_contacts_upload.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function (response) {
            // Reset button state
            $(btnId).attr('disabled', false);
            $(spinnerId).addClass('d-none');
            $(textId).text(isIndividual ? 'Add Contact' : 'Import Contacts');

            // Show SweetAlert2
            Swal.fire({
                title: response.status_code === 'error' ? 'Error' : 'Success',
                text: response.status,
                icon: response.status_code,
                confirmButtonText: 'OK'
            });

        if (response.status_code === 'success') {
                if (isIndividual) {
            // Reset individual form
            $(form).find('input[name="name"], input[name="phone_number"], input[name="email"], input[name="company"], textarea[name="notes"]').val('');
            $(form).find('select[name="group"]').val('All');
                } else {
            // Reset bulk form
            $(form).find('input[type="file"]').val('');
            $('#file-name').text('');  // Clear the displayed filename
            $(form).find('select[name="group"]').val('All');
        }
    }
        },
        error: function (xhr, status, error) {
            // Reset button state
            $(btnId).attr('disabled', false);
            $(spinnerId).addClass('d-none');
            $(textId).text(isIndividual ? 'Add Contact' : 'Import Contacts');

            // Show error alert
            Swal.fire({
                title: 'Error',
                text: 'Failed to process request: ' + (xhr.responseJSON?.status || error),
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
});

</script>