<?php
$pageTitle = 'Dashboard';
include_once '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\SMSClient;

// Initialize variables
$balance = 0;
$total_sms_sent = 0;
$success_rate = 0;
$total_contacts = 0;
$recent_logs = [];
$group_stats = [];

// Generate CSRF token
$csrf_token = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? Authentication::createToken()
    : ($_SESSION['csrf_token'] ?? '');

// Fetch total SMS sent
$conn = MySQLDatabase::createConnection();
if ($conn) {
    try {
        // Fetch total contacts
        $result = MySQLDatabase::sqlSelect(
            $conn,
            'SELECT COUNT(*) as total FROM contacts WHERE user_id = ?',
            'i',
            $_SESSION['USER_ID']
        );
        if ($result) {
            $total_contacts = $result->fetch_assoc()['total'];
            $result->free_result();
        }

        // Fetch total SMS sent
        $result = MySQLDatabase::sqlSelect(
            $conn,
            'SELECT COUNT(*) as total FROM sms_logs WHERE user_id = ?',
            'i',
            $_SESSION['USER_ID']
        );
        if ($result) {
            $total_sms_sent = $result->fetch_assoc()['total'];
            $result->free_result();
        }

        // Calculate success rate
        $result = MySQLDatabase::sqlSelect(
            $conn,
            'SELECT status, COUNT(*) as count FROM sms_logs WHERE user_id = ? GROUP BY status',
            'i',
            $_SESSION['USER_ID']
        );
        if ($result) {
            $total = 0;
            $success = 0;
            while ($row = $result->fetch_assoc()) {
                $total += $row['count'];
                if ($row['status'] === 'success') {
                    $success = $row['count'];
                }
            }
            $success_rate = $total > 0 ? round(($success / $total) * 100, 2) : 0;
            $result->free_result();
        }

        // Fetch recent logs
        $result = MySQLDatabase::sqlSelect(
            $conn,
            'SELECT phone_number, message, sent_at, status FROM sms_logs
             WHERE user_id = ? ORDER BY sent_at DESC LIMIT 5',
            'i',
            $_SESSION['USER_ID']
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_logs[] = $row;
            }
            $result->free_result();
        }

        // Get contact group statistics
        $result = MySQLDatabase::sqlSelect(
            $conn,
            'SELECT `group`, COUNT(*) as count FROM contacts
             WHERE user_id = ? GROUP BY `group`',
            'i',
            $_SESSION['USER_ID']
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $group_stats[] = $row;
            }
            $result->free_result();
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Database error: " . $e->getMessage();
        $_SESSION['status_code'] = "error";
    } finally {
        $conn->close();
    }
}

// Fetch SMS balance
try {
    $response = SMSClient::checkSMSBalance();
    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] === true) {
        $balance = $data['message'];
    }
} catch (Exception $e) {
    $_SESSION['message'] = "Failed to fetch SMS balance: " . $e->getMessage();
    $_SESSION['status_code'] = "error";
}
?>

<div class="container-fluid">
    <!-- Balance Warning -->
    <?php if ($balance < 5): ?>
        <div class="alert alert-warning">
            <strong>Low Balance!</strong> Your SMS balance is <?php echo $balance; ?>. Please top up to continue sending messages.
        </div>
    <?php endif; ?>

    <!-- Main Stats Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col-5 col-md-4">
                            <div class="icon-big text-center icon-warning">
                                <i class="nc-icon nc-credit-card text-primary"></i>
                            </div>
                        </div>
                        <div class="col-7 col-md-8">
                            <div class="numbers">
                                <p class="card-category">SMS Balance</p>
                                <p class="card-title text-sm" id="sms-balance"></p>
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
                        <i class="fa fa-history"></i>
                        Success Rate: <?php echo $success_rate; ?>%
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
                                <i class="nc-icon nc-single-02 text-primary"></i>
                            </div>
                        </div>
                        <div class="col-7 col-md-8">
                            <div class="numbers">
                                <p class="card-category">Total Contact List</p>
                                <p class="card-title"><?php echo number_format($total_contacts); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <hr />
                    <div class="stats">
                        <i class="fa fa-users"></i>
                        <a href="contact-list.php">Manage Contacts</a>
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
                                <i class="nc-icon nc-watch-time text-info"></i>
                            </div>
                        </div>
                        <div class="col-7 col-md-8">
                            <div class="numbers">
                                <p class="card-category">Recent SMS Logs</p>
                                <p class="card-title"><?php echo count($recent_logs); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <hr />
                    <div class="stats">
                        <i class="fa fa-history"></i>
                        <a href="sms-logs.php">View All SMS Logs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- SMS Status Chart -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Message Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="smsStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Group Statistics -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Contact Groups</h5>
                </div>
                <div class="card-body">
                    <div>
                        <table class="table">

                            <?php if (!empty($group_stats)): ?>
                                <thead>
                                    <tr>
                                        <th>Group Name</th>
                                        <th>Contacts</th>
                                    </tr>
                                </thead>
                            <?php endif; ?>
                            <tbody>
                                <?php if (empty($group_stats)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center p-4">
                                            <div class="empty-state">
                                                <i class="nc-icon nc-single-copy-04 text-muted mb-2" style="font-size: 2em;"></i>
                                                <p class="text-muted">No groups found. Create groups to organize your contacts.</p>
                                                <a href="groups.php" class="btn btn-primary btn-sm mt-2">Create Group</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php foreach ($group_stats as $group): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($group['group'] ?? 'Ungrouped'); ?></td>
                                        <td><?php echo number_format($group['count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Recent Messages</h5>
                </div>

                <div class="card-body">
                    <div class="">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Recipient</th>
                                    <th>Message</th>
                                    <th>Sent At</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center p-4">
                                            <div class="empty-state">
                                                <i class="nc-icon nc-chat-33 text-muted mb-3" style="font-size: 2em;"></i>
                                                <p class="text-muted">No SMS found. Send messages to get started.</p>
                                                <a href="send-sms.php" class="btn btn-primary btn-sm mt-2">Send SMS</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>

                                    <?php foreach ($recent_logs as $log): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($log['message'], 0, 50)) .
                                                    (strlen($log['message']) > 50 ? '...' : ''); ?></td>

                                            <td><?php
                                                $date = new DateTimeImmutable($log['sent_at']);
                                                echo $date->format('D, M j, Y \a\t g:i A');
                                                ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $log['status'] === 'success' ?
                                                                                'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once '../components/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        checkSMSBalance();
        initializeCharts();
    });

    function checkSMSBalance() {
        const balanceElement = document.getElementById('sms-balance');
        balanceElement.textContent = 'Checking..';

        fetch('../controllers/process_sms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=check_balance&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>`
            })
            .then(response => response.json())
            .then(data => {

                if (data.status_code === 'success') {
                    const credits = parseInt(data.balance);
                    balanceElement.textContent = `${credits}`;
                } else {
                    balanceElement.textContent = 'Failed to load';
                }
            })
            .catch(() => {
                balanceElement.textContent = 'Failed to load';
            });
    }

    function initializeCharts() {
        const ctx = document.getElementById('smsStatusChart').getContext('2d');
        const totalSMS = <?php echo $total_sms_sent; ?>;

        if (totalSMS === 0) {
            // Display "No data yet" message when there are no logs
            ctx.canvas.style.height = '200px';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = '16px Arial';
            ctx.fillStyle = '#6c757d'; // Bootstrap's text-muted color
            ctx.fillText('No message data available yet', ctx.canvas.width / 2, ctx.canvas.height / 2);

            // Add small icon and send SMS button below the message
            const chartContainer = ctx.canvas.parentElement;
            chartContainer.innerHTML = `
            <div class="text-center empty-chart-container py-4">
                <i class="nc-icon nc-chart-pie-36 text-muted mb-3" style="font-size: 2em;"></i>
                <p class="text-muted">No message data available yet</p>
                <a href="send-sms.php" class="btn btn-primary btn-sm mt-2">Send SMS</a>
            </div>
        `;
        } else {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Success', 'Failed'],
                    datasets: [{
                        data: [<?php echo "$success, " . ($total_sms_sent - $success); ?>],
                        backgroundColor: ['#390546', '#c73d6c'],
                        borderColor: ['#ffffff', '#ffffff'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }
</script>

<style>
    .alert-warning {
        margin-bottom: 20px;
        background-color: #c73d6c !important;
    }

    .card {
        margin-bottom: 20px;
    }

    .badge {
        padding: 5px 10px;
    }

    .btn-primary {
        background-color: #390546;
        border-color: #390546;
    }

    .btn-primary:hover {
        background-color: #4b0a5e !important;
    }

    .badge-success {
        background-color: #28a745;
    }

    .empty-chart-container {
        min-height: 200px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .nc-icon.text-muted {
        opacity: 0.6;
    }

    .badge-danger {
        background-color: #dc3545 !important;
    }

    .empty-state {
        text-align: center;
        padding: 20px;
    }

    canvas {
        min-height: 100px;
    }
</style>