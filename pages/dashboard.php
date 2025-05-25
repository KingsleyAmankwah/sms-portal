<?php
include '../components/header.php';
$pageTitle = 'Dashboard';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;

// Generate CSRF token
$csrf_token = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? Authentication::createToken()
    : ($_SESSION['csrf_token'] ?? '');

// Fetch total SMS sent
$conn = MySQLDatabase::createConnection();
$total_sms_sent = 0;
if ($conn) {
    $result = MySQLDatabase::sqlSelect(
        $conn,
        'SELECT COUNT(*) as total FROM sms_logs WHERE user_id = ? AND status = ?',
        'is',
        $_SESSION['USER_ID'],
        'success'
    );
    if ($result) {
        $row = $result->fetch_assoc();
        $total_sms_sent = $row['total'];
        $result->free_result();
    }
    $conn->close();
}
?>

<div class="row">
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
            <div class="card-body">
                <div class="row">
                    <div class="col-5 col-md-4">
                        <div class="icon-big text-center icon-warning">
                            <i class="nc-icon nc-email-85 text-primary"></i>
                        </div>
                    </div>
                    <div class="col-7 col-md-8">
                        <div class="numbers">
                            <p class="card-category">SMS Balance</p>
                            <p class="card-title text-sm" id="sms-balance">Checking...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <hr />
                <div class="stats">
                    <i class="fa fa-refresh"></i>
                    <a href="javascript:void(0)" onclick="checkSMSBalance()">Update Now</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
            <div class="card-body">
                <div class="row">
                    <div class="col-5 col-md-4">
                        <div class="icon-big text-center icon-warning">
                            <i class="nc-icon nc-money-coins text-success"></i>
                        </div>
                    </div>
                    <div class="col-7 col-md-8">
                        <div class="numbers">
                            <p class="card-category">Revenue</p>
                            <p class="card-title">$ 1,345</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <hr />
                <div class="stats">
                    <i class="fa fa-calendar-o"></i>
                    Last day
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
            <div class="card-body">
                <div class="row">
                    <div class="col-5 col-md-4">
                        <div class="icon-big text-center icon-warning">
                            <i class="nc-icon nc-vector text-danger"></i>
                        </div>
                    </div>
                    <div class="col-7 col-md-8">
                        <div class="numbers">
                            <p class="card-category">Errors</p>
                            <p class="card-title">23</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <hr />
                <div class="stats">
                    <i class="fa fa-clock-o"></i>
                    In the last hour
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
            <div class="card-body">
                <div class="row">
                    <div class="col-5 col-md-4">
                        <div class="icon-big text-center icon-warning">
                            <i class="nc-icon nc-send text-info"></i>
                        </div>
                    </div>
                    <div class="col-7 col-md-8">
                        <div class="numbers">
                            <p class="card-category">Total SMS Sent</p>
                            <p class="card-title"><?php echo number_format($total_sms_sent); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <hr />
                <div class="stats">
                    <i class="fa fa-paper-plane"></i>
                    <a href="sms.php">View SMS</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../components/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        checkSMSBalance();
    });

    function checkSMSBalance() {
        const balanceElement = document.getElementById('sms-balance');
        balanceElement.textContent = 'Checking...';

        fetch('../controllers/process_sms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=check_balance&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>`
            })
            .then(response => response.json())
            .then(data => {
                balanceElement.textContent = data.status_code === 'success' ?
                    `${data.balance} credits` :
                    'Failed to load';
            })
            .catch(() => {
                balanceElement.textContent = 'Failed to load';
            });
    }
</script>