<?php
$pageTitle = 'Send Messages';
require_once __DIR__ . '/../vendor/autoload.php';
include_once '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;

// Ensure customLog is defined
if (!function_exists('customLog')) {
    define('CUSTOM_LOG', 'C:\xampp\htdocs\dashboard-master\debug.log');
    function customLog($message)
    {
        file_put_contents(CUSTOM_LOG, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    }
}

// Generate CSRF token
$csrf_token = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? Authentication::createToken()
    : ($_SESSION['csrf_token'] ?? '');

// Fetch groups and contacts for dropdowns
$conn = MySQLDatabase::createConnection();
$groups = [];
$contacts = [];
if ($conn) {
    // Fetch groups
    $result = MySQLDatabase::sqlSelect($conn, 'SELECT name FROM groups WHERE user_id = ?', 'i', $_SESSION['USER_ID']);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row['name'];
        }
        $result->free_result();
    }
    // Fetch contacts
    $result = MySQLDatabase::sqlSelect($conn, 'SELECT name, phone_number FROM contacts WHERE user_id = ?', 'i', $_SESSION['USER_ID']);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contacts[] = $row;
        }
        $result->free_result();
    }
    $conn->close();
}

// Get preselected phone or group from URL (from contacts-list.php)
$preselected_phone = isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '';
$preselected_group = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : '';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card p-4">
                <div class="d-flex justify-content-between mb-4">
                    <h4 class="card-title">Send SMS</h4>
                </div>
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#individual">Individual SMS</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#bulk">Bulk SMS</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- Individual SMS Form -->
                    <div class="tab-pane fade show active" id="individual">
                        <form id="individual-sms-form" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="send_individual">
                            <div class="form-group">
                                <label for="sms-contact">Select Contact</label>
                                <select class="form-control" name="phone_number" id="sms-contact" required>
                                    <option value="">Select a contact</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?php echo htmlspecialchars($contact['phone_number']); ?>"
                                            <?php echo $contact['phone_number'] === $preselected_phone ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($contact['name'] . ' (' . $contact['phone_number'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="sms-message">Message (160 characters max)</label>
                                <textarea class="form-control" name="message" id="sms-message" rows="4" maxlength="160" placeholder="Enter your message" required></textarea>
                                <small class="form-text text-muted">Characters: <span id="sms-char-count">0</span>/160</small>
                            </div>
                            <div class="form-group">
                                <label for="">SMS Balance</label>
                                <p id="sms-balance-individual" class="form-text">Checking balance...</p>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" id="send-sms-btn">
                                <span class="spinner-border spinner-border-sm d-none" id="send-sms-spinner"></span>
                                <span id="send-sms-btn-text">Send SMS</span>
                            </button>
                        </form>
                    </div>
                    <!-- Bulk SMS Form -->
                    <div class="tab-pane fade" id="bulk">
                        <form id="bulk-sms-form" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="validate_bulk">
                            <div class="form-group">
                                <label for="bulk-group">Select Group</label>
                                <select class="form-control" name="group" id="bulk-group" required>
                                    <option value="">Select contact group</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g); ?>"
                                            <?php echo $g === $preselected_group ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="bulk-message">Message (160 characters max)</label>
                                <textarea class="form-control" name="message" id="bulk-message" rows="4" maxlength="160" placeholder="Enter your message" required></textarea>
                                <small class="form-text text-muted">Characters: <span id="bulk-char-count">0</span>/160</small>
                            </div>
                            <div class="form-group">
                                <label for="">SMS Balance</label>
                                <p id="sms-balance-bulk" class="form-text">Checking balance...</p>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" id="bulk-sms-btn">
                                <span class="spinner-border spinner-border-sm d-none" id="bulk-sms-spinner"></span>
                                <span id="bulk-sms-btn-text">Send SMS</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../components/footer.php'; ?>

<style>
    .card {
        margin-bottom: 20px;
    }

    a {
        color: #390546;
    }

    a:hover {
        color: #4b0a5e !important;
    }

    .btn-primary {
        background-color: #390546;
        border-color: #390546;
    }

    .btn-primary:hover {
        background-color: #4b0a5e !important;
    }

    .form-text {
        margin-bottom: 10px;
    }

    .nav-tabs .nav-link.active {
        background-color: #f8f9fa;
        border-color: #dee2e6 #dee2e6 #fff;
    }

    .tab-content {
        padding: 20px;
        background: #fff;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 0.25rem 0.25rem;
    }

    .border-left {
        border-left: 3px solid #dc3545 !important;
        padding-left: 1rem;
        margin: 0.5rem 0;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .text-muted {
        color: #6c757d !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Prioritize URL tab parameter
        const urlParams = new URLSearchParams(window.location.search);
        let activeTab = urlParams.get('tab') || 'individual';

        // Ensure valid tab
        if (!['individual', 'bulk'].includes(activeTab)) {
            activeTab = 'individual';
        }

        // Activate tab
        $(`.nav-link[href="#${activeTab}"]`).tab('show');

        // Update URL when tab is changed
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            const tabId = $(e.target).attr('href').substring(1);
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('tab', tabId);
            history.replaceState(null, '', newUrl);
        });

        // Check balance on page load
        checkSMSBalance();

        // Handle form submissions
        $('#individual-sms-form').on('submit', function(e) {
            e.preventDefault();
            sendIndividualSMS();
        });

        $('#bulk-sms-form').on('submit', function(e) {
            e.preventDefault();
            sendBulkSMS();
        });

        // Character count for SMS textareas
        $('#sms-message').on('input', function() {
            $('#sms-char-count').text(this.value.length);
        });

        $('#bulk-message').on('input', function() {
            $('#bulk-char-count').text(this.value.length);
        });

        function handleBulkSMSResponse(response, form) {
            let icon, title, message;

            switch (response.status_code) {
                case 'success':
                    icon = 'success';
                    title = 'Success';
                    message = response.status;
                    break;

                case 'partial_success':
                    icon = 'info';
                    title = 'Partially Successful';
                    message = `<div class="text-left">
                <p>${response.status}</p>`;

                    if (response.failed_recipients && response.failed_recipients.length > 0) {
                        message += `<p class="mt-2">Failed recipients:</p>
                <div class="text-danger small mt-2 border-left pl-3">
                    ${response.failed_recipients.join('<br>')}
                </div>`;
                    }
                    message += '</div>';
                    break;

                default:
                    icon = 'error';
                    title = 'Error';
                    message = response.status || 'Failed to send messages';
            }

            Swal.fire({
                title: title,
                html: message,
                icon: icon,
                confirmButtonText: 'OK',
                confirmButtonColor: '#390546',
                customClass: {
                    container: 'swal-wide'
                }
            });

            if (['success', 'partial_success'].includes(response.status_code)) {
                form[0].reset();
                $('#bulk-char-count').text('0');
                checkSMSBalance();
            }
        }


        function handleSMSResponse(response, form) {
            if (form.attr('id') === 'bulk-sms-form') {
                handleBulkSMSResponse(response, form);
            } else {
                // Handle individual SMS response
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
                        text: response.status_code,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
        }

        function handleAjaxError(xhr) {
            let errorMessage;
            try {
                const response = xhr.responseJSON || JSON.parse(xhr.responseText);
                errorMessage = response.message || 'Failed to send SMS';
            } catch (e) {
                console.error('Error parsing server response:', e);
                errorMessage = 'Server communication error';
            }

            Swal.fire({
                title: 'Error',
                text: errorMessage,
                icon: 'error',
                confirmButtonText: 'OK'
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
                success: function(response) {
                    if (response.status_code === 'success') {
                        const balanceText = `Available: ${response.balance} SMS credits`;
                        $('#sms-balance-individual').text(balanceText);
                        $('#sms-balance-bulk').text(balanceText);
                    } else {
                        const errorText = 'Failed to load balance, please check internet connection';
                        $('#sms-balance-individual').text(errorText);
                        $('#sms-balance-bulk').text(errorText);
                    }
                },
                error: function() {
                    const errorText = 'Failed to load balance, please check internet connection';
                    $('#sms-balance-individual').text(errorText);
                    $('#sms-balance-bulk').text(errorText);
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
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    handleSMSResponse(response, form);
                },
                error: function(xhr) {
                    handleAjaxError(xhr);
                },
                complete: function() {
                    // Reset form state
                    btn.attr('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Send SMS');
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
            btnText.text('Confirming...');

            $.ajax({
                url: '../controllers/process_sms.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status_code === 'validation_failed') {
                        Swal.fire({
                            title: 'Validation Failed',
                            html: `
                        <div class="text-left">
                            <p>${response.message}</p>
                            ${response.invalid_numbers ? `
                                <p class="mt-2">Failed validation:</p>
                                <div class="text-danger small mt-2 border-left pl-3">
                                    ${response.invalid_numbers.join('<br>')}
                                </div>
                            ` : ''}
                            ${response.required_credits ? `
                                <p class="mt-2">SMS Credits:</p>
                                <div class="text-danger small mt-2">
                                    Required: ${response.required_credits}<br>
                                    Available: ${response.available_credits}
                                </div>
                            ` : ''}
                        </div>`,
                            icon: 'warning',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#390546'
                        });
                    } else if (response.status_code === 'validation_success') {
                        Swal.fire({
                            title: 'Confirm Send',
                            html: `
                        <p>Ready to send messages to ${response.valid_count} recipients in group "${response.group_name}".</p>
                        <p class="text-muted small">This will use ${response.valid_count} SMS credits</p>
                    `,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Send Messages',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#390546',
                            cancelButtonColor: '#6c757d'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                proceedWithBulkSend(form, btn, spinner, btnText);
                            }
                        });
                    }
                },
                error: function(xhr) {
                    handleAjaxError(xhr);
                },
                complete: function() {
                    btn.attr('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Send SMS');
                }
            });
        }

        function proceedWithBulkSend(form, btn, spinner, btnText) {
            btn.attr('disabled', true);
            spinner.removeClass('d-none');
            btnText.text('Sending...');

            $.ajax({
                url: '../controllers/process_sms.php',
                type: 'POST',
                data: form.serialize().replace('validate_bulk', 'send_bulk'),
                dataType: 'json',
                success: function(response) {
                    handleBulkSMSResponse(response, form);
                },
                error: handleAjaxError,
                complete: function() {
                    btn.attr('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Send SMS');
                }
            });
        }
    });
</script>