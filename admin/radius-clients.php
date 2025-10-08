<?php
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');
require_once '../config/radius.php';

$db = new Database();
$conn = $db->getConnection();

$pageTitle = 'RADIUS Clients Management - ' . SITE_NAME;
$error = '';
$success = '';

// Get RADIUS connection
$radiusConn = getRadiusConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nasname = trim($_POST['nasname']);
                $shortname = trim($_POST['shortname']);
                $type = trim($_POST['type']);
                $secret = trim($_POST['secret']);
                $description = trim($_POST['description']);
                
                if (empty($nasname) || empty($secret)) {
                    $error = 'Server IP and Shared Secret are required!';
                } else {
                    try {
                        // Check if already exists
                        $stmt = $radiusConn->prepare("SELECT COUNT(*) as count FROM nas WHERE nasname = ?");
                        $stmt->execute([$nasname]);
                        if ($stmt->fetch()['count'] > 0) {
                            $error = 'This server IP already exists!';
                        } else {
                            $stmt = $radiusConn->prepare("INSERT INTO nas (nasname, shortname, type, secret, description) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$nasname, $shortname, $type, $secret, $description]);
                            $success = 'RADIUS client added successfully! Remember to restart FreeRADIUS.';
                        }
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $nasname = trim($_POST['nasname']);
                $shortname = trim($_POST['shortname']);
                $type = trim($_POST['type']);
                $secret = trim($_POST['secret']);
                $description = trim($_POST['description']);
                
                if (empty($nasname) || empty($secret)) {
                    $error = 'Server IP and Shared Secret are required!';
                } else {
                    try {
                        $stmt = $radiusConn->prepare("UPDATE nas SET nasname=?, shortname=?, type=?, secret=?, description=? WHERE id=?");
                        $stmt->execute([$nasname, $shortname, $type, $secret, $description, $id]);
                        $success = 'RADIUS client updated successfully! Remember to restart FreeRADIUS.';
                    } catch (PDOException $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                try {
                    $stmt = $radiusConn->prepare("DELETE FROM nas WHERE id=?");
                    $stmt->execute([$id]);
                    $success = 'RADIUS client deleted successfully! Remember to restart FreeRADIUS.';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
                
            case 'test':
                $nasname = trim($_POST['nasname']);
                // Test connectivity (ping)
                $output = [];
                exec("ping -c 1 -W 2 " . escapeshellarg($nasname) . " 2>&1", $output, $return_var);
                if ($return_var === 0) {
                    $success = "✅ Server $nasname is reachable!";
                } else {
                    $error = "❌ Server $nasname is not reachable!";
                }
                break;
        }
    }
}

// Get all RADIUS clients
$clients = [];
if ($radiusConn) {
    try {
        $stmt = $radiusConn->query("SELECT * FROM nas ORDER BY id DESC");
        $clients = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Failed to fetch RADIUS clients: ' . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<style>
.radius-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.radius-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.btn-add {
    background: #28a745;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-add:hover {
    background: #218838;
    color: white;
}

.clients-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.clients-table th,
.clients-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.clients-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.clients-table tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-sstp {
    background: #007bff;
    color: white;
}

.badge-other {
    background: #6c757d;
    color: white;
}

.btn-group {
    display: flex;
    gap: 5px;
}

.btn-sm {
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.btn-edit {
    background: #ffc107;
    color: #000;
}

.btn-test {
    background: #17a2b8;
    color: white;
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.btn-sm:hover {
    opacity: 0.8;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
    min-height: 60px;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.btn-submit {
    background: #007bff;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    width: 100%;
}

.btn-submit:hover {
    background: #0056b3;
}

.alert {
    padding: 12px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.secret-display {
    display: flex;
    align-items: center;
    gap: 10px;
}

.secret-hidden {
    letter-spacing: 2px;
}

.btn-show-secret {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    font-size: 12px;
    padding: 0;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    font-size: 64px;
    color: #ddd;
    margin-bottom: 20px;
}

.info-box {
    background: #e7f3ff;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.info-box h4 {
    margin: 0 0 10px 0;
    color: #004085;
}

.info-box p {
    margin: 0;
    color: #004085;
    font-size: 14px;
}

.restart-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 10px 15px;
    margin-top: 20px;
    border-radius: 4px;
    font-size: 13px;
}
</style>

<div class="content-wrapper">
    <div class="radius-card">
        <div class="radius-header">
            <div>
                <h2>🔐 RADIUS Clients Management</h2>
                <p style="color: #666; margin: 5px 0 0 0;">Manage SSTP servers that can authenticate via RADIUS</p>
            </div>
            <button class="btn-add" onclick="openAddModal()">
                ➕ Add SSTP Server
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!RADIUS_ENABLED): ?>
            <div class="info-box">
                <h4>⚠️ RADIUS is Currently Disabled</h4>
                <p>RADIUS authentication is disabled in config/radius.php. Enable it to use this feature.</p>
            </div>
        <?php endif; ?>

        <?php if (!$radiusConn): ?>
            <div class="alert alert-error">
                ❌ Cannot connect to RADIUS database. Please check your configuration.
            </div>
        <?php elseif (empty($clients)): ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px;">🔐</div>
                <h3>No RADIUS Clients Yet</h3>
                <p>Add your first SSTP server to start using RADIUS authentication.</p>
                <br>
                <button class="btn-add" onclick="openAddModal()">➕ Add Your First Server</button>
            </div>
        <?php else: ?>
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Server IP</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Shared Secret</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($client['nasname']); ?></strong></td>
                            <td><?php echo htmlspecialchars($client['shortname'] ?: '-'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $client['type'] === 'other' ? 'other' : 'sstp'; ?>">
                                    <?php echo strtoupper(htmlspecialchars($client['type'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="secret-display">
                                    <span class="secret-text secret-hidden" data-secret="<?php echo htmlspecialchars($client['secret']); ?>">
                                        ••••••••••
                                    </span>
                                    <button class="btn-show-secret" onclick="toggleSecret(this)">Show</button>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($client['description'] ?: '-'); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn-sm btn-test" onclick="testClient('<?php echo htmlspecialchars($client['nasname']); ?>')">
                                        🔍 Test
                                    </button>
                                    <button class="btn-sm btn-edit" onclick='editClient(<?php echo json_encode($client); ?>)'>
                                        ✏️ Edit
                                    </button>
                                    <button class="btn-sm btn-delete" onclick="deleteClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['nasname']); ?>')">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="restart-warning">
                ⚠️ <strong>Important:</strong> After adding/editing/deleting clients, restart FreeRADIUS:
                <code style="background: #fff; padding: 2px 6px; border-radius: 3px; margin-left: 10px;">
                    sudo systemctl restart freeradius
                </code>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="clientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add SSTP Server</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" id="clientForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="clientId" value="">
            
            <div class="form-group">
                <label>Server IP Address *</label>
                <input type="text" name="nasname" id="nasname" required placeholder="e.g., 103.117.149.112">
                <small>The public IP address of your SSTP server</small>
            </div>
            
            <div class="form-group">
                <label>Server Name</label>
                <input type="text" name="shortname" id="shortname" placeholder="e.g., sstp-server-1">
                <small>A friendly name for this server (optional)</small>
            </div>
            
            <div class="form-group">
                <label>Server Type</label>
                <select name="type" id="type">
                    <option value="other">Other (SSTP/Generic)</option>
                    <option value="cisco">Cisco</option>
                    <option value="mikrotik">MikroTik</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Shared Secret *</label>
                <input type="text" name="secret" id="secret" required placeholder="e.g., testing123">
                <small>Must match the secret configured on your SSTP server</small>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="description" placeholder="e.g., Main SSTP server in Singapore"></textarea>
            </div>
            
            <button type="submit" class="btn-submit">💾 Save RADIUS Client</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- Test Form -->
<form method="POST" id="testForm" style="display: none;">
    <input type="hidden" name="action" value="test">
    <input type="hidden" name="nasname" id="testNasname">
</form>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add SSTP Server';
    document.getElementById('formAction').value = 'add';
    document.getElementById('clientForm').reset();
    document.getElementById('clientId').value = '';
    document.getElementById('clientModal').style.display = 'block';
}

function editClient(client) {
    document.getElementById('modalTitle').textContent = 'Edit SSTP Server';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('clientId').value = client.id;
    document.getElementById('nasname').value = client.nasname;
    document.getElementById('shortname').value = client.shortname || '';
    document.getElementById('type').value = client.type;
    document.getElementById('secret').value = client.secret;
    document.getElementById('description').value = client.description || '';
    document.getElementById('clientModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('clientModal').style.display = 'none';
}

function deleteClient(id, nasname) {
    if (confirm(`Are you sure you want to delete RADIUS client "${nasname}"?\n\nThis will prevent this server from authenticating users via RADIUS.`)) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function testClient(nasname) {
    if (confirm(`Test connectivity to ${nasname}?\n\nThis will ping the server to check if it's reachable.`)) {
        document.getElementById('testNasname').value = nasname;
        document.getElementById('testForm').submit();
    }
}

function toggleSecret(button) {
    const secretSpan = button.previousElementSibling;
    const isHidden = secretSpan.classList.contains('secret-hidden');
    
    if (isHidden) {
        secretSpan.textContent = secretSpan.dataset.secret;
        secretSpan.classList.remove('secret-hidden');
        button.textContent = 'Hide';
    } else {
        secretSpan.textContent = '••••••••••';
        secretSpan.classList.add('secret-hidden');
        button.textContent = 'Show';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('clientModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
