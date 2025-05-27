<?php
$pageTitle = 'Account Settings';
require_once __DIR__ . '/../vendor/autoload.php';
include_once '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;

// Fetch user details
$conn = MySQLDatabase::createConnection();
$user = null;
if ($conn) {
    $result = MySQLDatabase::sqlSelect(
        $conn,
        'SELECT username, email FROM users WHERE id = ?',
        'i',
        $_SESSION['USER_ID']
    );
    if ($result) {
        $user = $result->fetch_assoc();
        $result->free_result();
    }
    $conn->close();
}

// Generate CSRF token
$csrf_token = Authentication::createToken();
$pageTitle = 'Account Settings';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card p-4">
                <h4 class="card-title mb-4">Account Settings</h4>
                <form id="account-settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="update_account">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" class="form-control" name="username" id="name"
                            value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" name="email" id="email"
                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" name="password" id="password"
                            placeholder="Enter new password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password"
                            placeholder="Confirm new password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="update-account-btn">
                        <span class="spinner-border spinner-border-sm d-none" id="update-account-spinner"></span>
                        <span id="update-account-btn-text">Update Account</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once '../components/footer.php'; ?>

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
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('account-settings-form');
        const btn = document.getElementById('update-account-btn');
        const spinner = document.getElementById('update-account-spinner');
        const btnText = document.getElementById('update-account-btn-text');

        form.addEventListener('submit', e => {
            e.preventDefault();

            btn.disabled = true;
            spinner.classList.remove('d-none');
            btnText.textContent = 'Updating...';

            const formData = new FormData(form);
            fetch('../controllers/process_account.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                    btnText.textContent = 'Update Account';

                    Swal.fire({
                        title: data.status_code === 'success' ? 'Success' : 'Error',
                        text: data.status,
                        icon: data.status_code,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (data.status_code === 'success') {
                            // Fetch updated user data
                            fetch('../controllers/process_account.php', {
                                    method: 'POST',
                                    body: JSON.stringify({
                                        action: 'get_user_details',
                                        csrf_token: formData.get('csrf_token')
                                    }),
                                    headers: {
                                        'Content-Type': 'application/json'
                                    }
                                })
                                .then(response => response.json())
                                .then(userData => {
                                    if (userData.status_code === 'success') {
                                        // Update form with new data
                                        form.querySelector('input[name="username"]').value = userData.data.username;
                                        form.querySelector('input[name="email"]').value = userData.data.email;

                                        // Clear password fields
                                        form.querySelector('input[name="password"]').value = '';
                                        form.querySelector('input[name="confirm_password"]').value = '';
                                    }
                                })
                                .catch(error => console.error('Error fetching updated user data:', error));
                        }
                    });
                })
                .catch(error => {
                    btn.disabled = false;
                    spinner.classList.add('d-none');
                    btnText.textContent = 'Update Account';

                    let errorMsg = 'Failed to update account';
                    try {
                        const response = JSON.parse(error.responseText || '{}');
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
                });
        });
    });
</script>