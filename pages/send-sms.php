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

// Get preselected phone or group from URL (from contacts-list.php)
$preselected_phone = isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '';
$preselected_group = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : '';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Bulk SMS Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title">Send Bulk SMS</h4>
                </div>
                <div class="card-body">
                    <form id="bulk-sms-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="form-group">
                            <label for="bulk-group">Select Group</label>
                            <select class="form-control" name="group" id="bulk-group">
                                <option value="All" <?php echo $preselected_group === 'All' ? 'selected' : ''; ?>>All Contacts</option>
                                <!-- Groups populated via AJAX -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bulk-message">Message (160 characters max)</label>
                            <textarea class="form-control" name="message" id="bulk-message" rows="4" maxlength="160" placeholder="Enter your message" required></textarea>
                            <small class="form-text text-muted">Characters: <span id="bulk-char-count">0</span>/160</small>
                        </div>
                        <div class="form-group">
                            <label>SMS Balance</label>
                            <p id="sms-balance" class="form-text">Checking balance...</p>
                        </div>
                        <button type="submit" class="btn btn-primary" id="bulk-sms-btn">
                            <span class="spinner-border spinner-border-sm d-none" id="bulk-sms-spinner"></span>
                            <span id="bulk-sms-btn-text">Send SMS</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Individual SMS Section -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Send Individual SMS</h4>
                </div>
                <div class="card-body">
                    <form id="individual-sms-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="form-group">
                            <label for="sms-contact">Select Contact</label>
                            <select class="form-control" name="phone_number" id="sms-contact">
                                <option value="">Select a contact</option>
                                <!-- Contacts populated via AJAX -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sms-message">Message (160 characters max)</label>
                            <textarea class="form-control" name="message" id="sms-message" rows="4" maxlength="160" placeholder="Enter your message" required></textarea>
                            <small class="form-text text-muted">Characters: <span id="sms-char-count">0</span>/160</small>
                        </div>
                        <button type="submit" class="btn btn-primary" id="send-sms-btn">
                            <span class="spinner-border spinner-border-sm d-none" id="send-sms-spinner"></span>
                            <span id="send-sms-btn-text">Send SMS</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../components/footer.php'; ?>

<style>
.card {
    margin-bottom: 20px;
}
.btn-primary {
    background-color: #390546;
    border-color: #390546;
}
.btn-primary:hover {
    background-color: #4b0a5e;
    border-color: #4b0a5e;
}
.form-text {
    margin-bottom: 10px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Load groups, contacts, and balance on page load
    loadGroups();
    loadContacts();
    checkSMSBalance();

    // Handle form submissions
    $('#bulk-sms-form').on('submit', function (e) {
        e.preventDefault();
        sendBulkSMS();
    });

    $('#individual-sms-form').on('submit', function (e) {
        e.preventDefault();
        sendIndividualSMS();
    });

    // Character count for SMS textareas
    $('#bulk-message').on('input', function () {
        $('#bulk-char-count').text(this.value.length);
    });

    $('#sms-message').on('input', function () {
        $('#sms-char-count').text(this.value.length);
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
                    const bulkGroup = $('#bulk-group');
                    bulkGroup.empty().append('<option value="All">All Contacts</option>');
                    if (response.groups && response.groups.length > 0) {
                        response.groups.forEach(group => {
                            const selected = group.name === '<?php echo $preselected_group; ?>' ? 'selected' : '';
                            bulkGroup.append(`<option value="${group.name}" ${selected}>${group.name}</option>`);
                        });
                    }
                }
            },
            error: function () {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to load groups',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    function loadContacts() {
        $.ajax({
            url: '../controllers/process_contacts.php',
            type: 'POST',
            data: {
                action: 'fetch',
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function (response) {
                if (response.status_code === 'success') {
                    const smsContact = $('#sms-contact');
                    smsContact.empty().append('<option value="">Select a contact</option>');
                    if (response.contacts && response.contacts.length > 0) {
                        response.contacts.forEach(contact => {
                            const selected = contact.phone_number === '<?php echo $preselected_phone; ?>' ? 'selected' : '';
                            smsContact.append(`<option value="${contact.phone_number}" ${selected}>${contact.name} (${contact.phone_number})</option>`);
                        });
                    }
                }
            },
            error: function () {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to load contacts',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    function checkSMSBalance() {
        $.ajax({
            url: '../controllers/process_sms.php',
            type: 'POST',
            data: {
                action: 'check_balance',
                csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
            },
            dataType: 'json',
            success: function (response) {
                if (response.status_code === 'success') {
                    $('#sms-balance').text(`Available: ${response.balance} SMS credits`);
                } else {
                    $('#sms-balance').text('Failed to load balance');
                }
            },
            error: function () {
                $('#sms-balance').text('Failed to load balance');
            }
        });
    }

    function sendBulkSMS() {
        const form = $('#bulk-sms-form');
        const btn = $('#bulk-sms-btn');
        const spinner = $('#bulk-sms-spinner');
        const btnText = $('#bulk-sms-btn-text');

        btn.attr('disabled', true);
        spinner.removeClass('d-none');
        btnText.text('Sending...');

        $.ajax({
            url: '../controllers/process_sms.php',
            type: 'POST',
            data: form.serialize() + '&action=send_bulk',
            dataType: 'json',
            success: function (response) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Send SMS');

                if (response.status_code === 'success') {
                    form[0].reset();
                    $('#bulk-char-count').text('0');
                    checkSMSBalance();
                    Swal.fire({
                        title: 'Success',
                        text: response.status,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.status || 'Failed to send SMS',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function (xhr) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Send SMS');

                Swal.fire({
                    title: 'Error',
                    text: 'Failed to send SMS: ' + (xhr.responseJSON?.status || 'Server error'),
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }

    function sendIndividualSMS() {
        const form = $('#individual-sms-form');
        const btn = $('#send-sms-btn');
        const spinner = $('#send-sms-spinner');
        const btnText = $('#send-sms-btn-text');

        btn.attr('disabled', true);
        spinner.removeClass('d-none');
        btnText.text('Sending...');

        $.ajax({
            url: '../controllers/process_sms.php',
            type: 'POST',
            data: form.serialize() + '&action=send_individual',
            dataType: 'json',
            success: function (response) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Send SMS');

                if (response.status_code === 'success') {
                    form[0].reset();
                    $('#sms-char-count').text('0');
                    checkSMSBalance();
                    Swal.fire({
                        title: 'Success',
                        text: response.status,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.message || response.status || 'Failed to send SMS',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function (xhr) {
                btn.attr('disabled', false);
                spinner.addClass('d-none');
                btnText.text('Send SMS');

                let errorMsg = 'Failed to send SMS';
            try {
                const response = xhr.responseJSON || JSON.parse(xhr.responseText);
                errorMsg = response.message || response.status || errorMsg;
            } catch (e) {
                errorMsg = 'Server error';
            }
                Swal.fire({
                    title: 'Error',
                    text: errorMsg,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }
});
</script>