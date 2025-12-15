<?php
session_start();
if (!isset($_SESSION['user_id'])) {
header('Location: auth/login.php');
 exit();
}
require_once 'db.php';
createVehicleTables();
try {
$result = $conn->query("SELECT COUNT(*) AS total FROM nodes");
$row = $result->fetch_assoc();
$totalNodes = $row['total'] ?? 0;
$result = $conn->query("SELECT COUNT(*) AS online FROM nodes WHERE status = 'online'");
$row = $result->fetch_assoc();
$onlineNodes = $row['online'] ?? 0;
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) AS incidents FROM incidents WHERE DATE(reported_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$todayIncidents = ($result->fetch_assoc())['incidents'] ?? 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS violations FROM violations WHERE DATE(violation_time) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$todayViolations = ($result->fetch_assoc())['violations'] ?? 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM vehicles");
$row = $result->fetch_assoc();
$totalVehicles = $row['total'] ?? 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM owners");
$row = $result->fetch_assoc();
$totalOwners = $row['total'] ?? 0;
$sql = "
SELECT i.*, n.name AS node_name
FROM incidents i
LEFT JOIN nodes n ON i.node_id = n.id
ORDER BY reported_at DESC
LIMIT 5
    ";
$result = $conn->query($sql);
$recentIncidents = $result->fetch_all(MYSQLI_ASSOC);
$sql = "
SELECT v.*, n.name AS node_name
FROM violations v
LEFT JOIN nodes n ON v.node_id = n.id
ORDER BY violation_time DESC
 LIMIT 5
    ";
$result = $conn->query($sql);
$recentViolations = $result->fetch_all(MYSQLI_ASSOC);
$sql = "
SELECT v.*, vt.type_name, o.full_name 
FROM vehicles v
LEFT JOIN vehicle_types vt ON v.type_id = vt.id
LEFT JOIN owners o ON v.owner_id = o.id
ORDER BY v.registered_at DESC
LIMIT 5
    ";
$result = $conn->query($sql);
$recentVehicles = $result->fetch_all(MYSQLI_ASSOC);
$nodeHealth = ($totalNodes > 0)
? round(($onlineNodes / $totalNodes) * 100)
        : 0;
$sql = "
SELECT vt.type_name, COUNT(v.id) as count
FROM vehicles v
RIGHT JOIN vehicle_types vt ON v.type_id = vt.id
GROUP BY vt.id
ORDER BY count DESC
    ";
$result = $conn->query($sql);
$vehiclesByType = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
$totalNodes = 0;
$onlineNodes = 0;
$todayIncidents = 0;
$todayViolations = 0;
$totalVehicles = 0;
$totalOwners = 0;
$recentIncidents = [];
$recentViolations = [];
$recentVehicles = [];
$vehiclesByType = [];
 $nodeHealth = 0;
}
$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest');
$userRole = htmlspecialchars($_SESSION['role'] ?? '');
function createVehicleTables() {
    global $conn;
$sql = "
CREATE TABLE IF NOT EXISTS vehicle_types (
id INT AUTO_INCREMENT PRIMARY KEY,
type_name VARCHAR(50) NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
$conn->query($sql);
$result = $conn->query("SELECT COUNT(*) as count FROM vehicle_types");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
$types = ['Sedan', 'SUV', 'Truck', 'Bus', 'Motorcycle'];
foreach ($types as $type) {
 $stmt = $conn->prepare("INSERT INTO vehicle_types (type_name) VALUES (?)");
 $stmt->bind_param("s", $type);
 $stmt->execute();
        }
    }
$sql = "
CREATE TABLE IF NOT EXISTS owners (
id INT AUTO_INCREMENT PRIMARY KEY,
full_name VARCHAR(100) NOT NULL,
phone VARCHAR(20),
address TEXT,
license_number VARCHAR(50),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_phone (phone),
INDEX idx_license (license_number)
        )
    ";
$conn->query($sql);
$sql = "
CREATE TABLE IF NOT EXISTS vehicles (
id INT AUTO_INCREMENT PRIMARY KEY,
plate_number VARCHAR(20) UNIQUE NOT NULL,
type_id INT,
model VARCHAR(50),
year YEAR,
color VARCHAR(30),
owner_id INT,
registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (type_id) REFERENCES vehicle_types(id) ON DELETE SET NULL,
FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE SET NULL,
INDEX idx_plate (plate_number),
INDEX idx_owner (owner_id),
 INDEX idx_type (type_id)
        )
    ";
    $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Traffic Monitoring System</title>
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.css">
</head>
<body>
<div class="container">
        <!-- Sidebar -->
<aside class="sidebar" id="sidebar">
<div class="sidebar-header">
<div class="logo">
<img src="assets/images/logo.png" alt="Logo">
<h2>TrafficSense</h2>
</div>
<button class="toggle-sidebar" id="toggleSidebar">
<i class="fas fa-bars"></i>
</button>
</div>
<div class="sidebar-menu">
<ul>
<li class="active">
<a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
</li>
<li><a href="vehicle.php"><i class="fas fa-car"></i> Vehicles <span class="badge"><?php echo $totalVehicles; ?></span></a></li>
<li><a href="owners.php"><i class="fas fa-users"></i> Owners <span class="badge"><?php echo $totalOwners; ?></span></a></li>
<li><a href="nodes.php"><i class="fas fa-satellite-dish"></i> Nodes <span class="badge"><?php echo $totalNodes; ?></span></a></li>
<li><a href="cameras.php"><i class="fas fa-video"></i> Cameras</a></li>
<li><a href="traffic_data.php"><i class="fas fa-chart-line"></i> Traffic Data</a></li>
<li><a href="accidents.php"><i class="fas fa-car-crash"></i> Accidents <span class="badge badge-danger"><?php echo $todayIncidents; ?></span></a></li>
<li><a href="violations.php"><i class="fas fa-exclamation-triangle"></i> Violations <span class="badge badge-warning"><?php echo $todayViolations; ?></span></a></li>
<li class="menu-divider"></li>
<li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>
<div class="sidebar-footer">
<div class="system-status">
<h4>System Status</h4>
<div class="status-item">
<span class="status-dot <?php echo ($nodeHealth > 80) ? 'online' : (($nodeHealth > 50) ? 'warning' : 'offline'); ?>"></span>
<span>Nodes Health: <?php echo $nodeHealth; ?>%</span>
</div>
</div>
</div>
</aside>
<!-- Main Content -->
<main class="main-content">
<nav class="navbar">
<div class="navbar-left">
<h1>Dashboard</h1>
<div class="breadcrumb">
<span class="active">Dashboard</span>
</div>
</div>
<div class="navbar-right">
<div class="user-menu">
<div class="user-info">
<span class="user-name"><?php echo $userName; ?></span>
<span class="user-role"><?php echo $userRole; ?></span>
</div>
<div class="user-avatar"><i class="fas fa-user-circle"></i></div>
<div class="dropdown-menu">
<a href="profile.php"><i class="fas fa-user"></i> Profile</a>
<a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
<div class="divider"></div>
<a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
</div>
<div class="notification-bell">
<i class="fas fa-bell"></i>
<span class="notification-count">3</span>
</div>
<button class="btn-theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
</div>
 </nav>
 <!-- Stats Cards -->
<div class="stats-grid">
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-car"></i></div>
<div class="stat-info"><h3><?php echo $totalVehicles; ?></h3><p>Total Vehicles</p></div>
<div class="stat-trend <?php echo ($totalVehicles > 0) ? 'positive' : 'negative'; ?>">
<i class="fas fa-arrow-up"></i><span>Registered</span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-users"></i></div>
<div class="stat-info"><h3><?php echo $totalOwners; ?></h3><p>Total Owners</p></div>
<div class="stat-trend <?php echo ($totalOwners > 0) ? 'positive' : 'negative'; ?>">
<i class="fas fa-arrow-up"></i><span>Registered</span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-satellite-dish"></i></div>
<div class="stat-info"><h3><?php echo $totalNodes; ?></h3><p>Total Nodes</p></div>
<div class="stat-trend <?php echo ($onlineNodes > 0) ? 'positive' : 'negative'; ?>">
<i class="fas fa-arrow-up"></i><span><?php echo $onlineNodes; ?> online</span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-car-crash"></i></div>
<div class="stat-info"><h3><?php echo $todayIncidents; ?></h3><p>Today's Incidents</p></div>
<div class="stat-trend <?php echo ($todayIncidents > 5) ? 'negative' : 'positive'; ?>">
<i class="fas fa-arrow-<?php echo ($todayIncidents > 5) ? 'up' : 'down'; ?>"></i>
<span><?php echo ($todayIncidents > 0) ? 'Active' : 'None'; ?></span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
<div class="stat-info"><h3><?php echo $todayViolations; ?></h3><p>Violations Today</p></div>
 <div class="stat-trend <?php echo ($todayViolations > 10) ? 'negative' : 'positive'; ?>">
 <i class="fas fa-arrow-<?php echo ($todayViolations > 10) ? 'up' : 'down'; ?>"></i>
<span><?php echo ($todayViolations > 0) ? 'Reported' : 'Clean'; ?></span>
</div>
</div>
<div class="stat-card">
<div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
<div class="stat-info"><h3><?php echo $nodeHealth; ?>%</h3><p>System Health</p></div>
<div class="stat-trend <?php echo ($nodeHealth > 80) ? 'positive' : (($nodeHealth > 50) ? 'warning' : 'negative'); ?>">
<i class="fas fa-heart"></i><span><?php echo ($nodeHealth > 80) ? 'Healthy' : 'Monitor'; ?></span>
</div>
</div>
</div>
 <!-- Charts and Activity Grid -->
<div class="dashboard-content">
<!-- Left Column: Charts -->
 <div class="dashboard-left">
<!-- Traffic Volume Chart -->
<div class="chart-card">
<div class="chart-header">
<h3><i class="fas fa-chart-line"></i> Traffic Volume (Last 24 Hours)</h3>
<select class="chart-filter" onchange="updateTrafficChart(this.value)">
<option value="24h">24 Hours</option>
<option value="7d">7 Days</option>
<option value="30d">30 Days</option>
</select>
</div>
<div id="trafficChart"></div>
</div>
<!-- Vehicles by Type Chart -->
<div class="chart-card">
<div class="chart-header">
<h3><i class="fas fa-car"></i> Vehicles by Type</h3>
</div>
<div class="vehicles-type-stats">
<?php if (!empty($vehiclesByType)): ?>
<?php foreach ($vehiclesByType as $type): ?>
<div class="type-item">
 <div class="type-info">
<span class="type-name"><?php echo htmlspecialchars($type['type_name']); ?></span>
<span class="type-count"><?php echo $type['count']; ?></span>
</div>
<div class="type-bar">
<div class="type-progress" style="width: <?php echo ($totalVehicles > 0) ? ($type['count'] / $totalVehicles * 100) : 0; ?>%"></div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="empty-chart">
<i class="fas fa-car"></i>
<p>No vehicles registered yet</p>
</div>
<?php endif; ?>
</div>
</div>
</div>
<!-- Right Column: Activity Lists -->
<div class="dashboard-right">
<!-- Recent Vehicles -->
<div class="activity-card">
<div class="activity-header">
<h3><i class="fas fa-car"></i> Recently Registered Vehicles</h3>
 <a href="vehicles.php" class="view-all">View All</a>
    </div>
<div class="activity-list">
<?php if (!empty($recentVehicles)): ?>
<?php foreach ($recentVehicles as $vehicle): ?>
<div class="activity-item">
<div class="activity-icon vehicle">
<i class="fas fa-car"></i>
</div>
<div class="activity-details">
<h4><?php echo htmlspecialchars($vehicle['plate_number']); ?></h4>
<p>
<?php echo htmlspecialchars($vehicle['type_name']); ?> • 
<?php echo htmlspecialchars($vehicle['model'] ?? 'Unknown'); ?> • 
<?php echo htmlspecialchars($vehicle['color'] ?? 'Unknown'); ?>
</p>
<p class="owner-info">
<i class="fas fa-user"></i> <?php echo htmlspecialchars($vehicle['full_name'] ?? 'Unknown Owner'); ?>
</p>
</div>
<div class="activity-time">
<?php echo date('H:i', strtotime($vehicle['registered_at'])); ?>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="empty-state">
<i class="fas fa-car"></i>
<p>No vehicles registered</p>
<a href="vehicles.php?action=add" class="btn-add">Add First Vehicle</a>
</div>
<?php endif; ?>
</div>
</div>
<!-- Recent Incidents -->
<div class="activity-card">
<div class="activity-header">
<h3><i class="fas fa-car-crash"></i> Recent Incidents</h3>
<a href="accidents.php" class="view-all">View All</a>
</div>
<div class="activity-list">
<?php if (!empty($recentIncidents)): ?>
<?php foreach ($recentIncidents as $incident): ?>
<div class="activity-item">
<div class="activity-icon <?php echo strtolower($incident['severity']); ?>">
<i class="fas fa-exclamation-circle"></i>
</div>
<div class="activity-details">
<h4><?php echo htmlspecialchars($incident['type']); ?></h4>
<p><?php echo htmlspecialchars($incident['node_name'] ?? 'Unknown Node'); ?></p>
<p class="incident-desc"><?php echo htmlspecialchars(substr($incident['description'] ?? '', 0, 50)); ?>...</p>
</div>
<div class="activity-time">
<?php echo date('H:i', strtotime($incident['reported_at'])); ?>
</div>
</div>
 <?php endforeach; ?>
<?php else: ?>
<div class="empty-state">
<i class="fas fa-check-circle"></i>
 <p>No incidents today</p>
</div>
<?php endif; ?>
</div>
</div>
 <!-- Recent Violations -->
<div class="activity-card">
<div class="activity-header">
<h3><i class="fas fa-exclamation-triangle"></i> Recent Violations</h3>
<a href="violations.php" class="view-all">View All</a>
</div>
<div class="activity-list">
<?php if (!empty($recentViolations)): ?>
<?php foreach ($recentViolations as $violation): ?>
<div class="activity-item">
<div class="activity-icon violation">
<i class="fas fa-car"></i>
</div>
<div class="activity-details">
<h4><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $violation['violation_type']))); ?></h4>
<p>
<?php if (!empty($violation['vehicle_number'])): ?>
<i class="fas fa-tag"></i> <?php echo htmlspecialchars($violation['vehicle_number']); ?>
 <?php else: ?>
<i class="fas fa-eye-slash"></i> No plate captured
 <?php endif; ?>
 </p>
<?php if ($violation['violation_type'] == 'speeding'): ?>
<p class="speed-info">
<i class="fas fa-tachometer-alt"></i> 
<?php echo $violation['speed_actual']; ?> km/h in <?php echo $violation['speed_limit']; ?> zone
</p>
<?php endif; ?>
</div>
<div class="activity-time">
<?php echo date('H:i', strtotime($violation['violation_time'])); ?>
 </div>
</div>
 <?php endforeach; ?>
 <?php else: ?>
<div class="empty-state">
<i class="fas fa-check-circle"></i>
<p>No violations today</p>
</div>
<?php endif; ?>
 </div>
</div>
</div>
</div>
<!-- Quick Actions Section -->
<div class="quick-actions">
<h3><i class="fas fa-bolt"></i> Quick Actions</h3>
<div class="actions-grid">
<a href="vehicle.php?action=add" class="action-card">
<div class="action-icon">
<i class="fas fa-plus-circle"></i>
</div>
<h4>Add New Vehicle</h4>
<p>Register a new vehicle in the system</p>
</a>
<a href="owners.php?action=add" class="action-card">
<div class="action-icon">
<i class="fas fa-user-plus"></i>
</div>
<h4>Add New Owner</h4>
<p>Register a new vehicle owner</p>
 </a>
<a href="violations.php?action=add" class="action-card">
<div class="action-icon">
<i class="fas fa-exclamation-circle"></i>
</div>
<h4>Report Violation</h4>
<p>Manually report a traffic violation</p>
</a>
<a href="accidents.php?action=add" class="action-card">
<div class="action-icon">
<i class="fas fa-ambulance"></i>
 </div>
<h4>Report Accident</h4>
<p>Manually report a traffic accident</p>
</a>
<a href="vehicles.php?action=search" class="action-card">
<div class="action-icon">
<i class="fas fa-search"></i>
 </div>
<h4>Search Vehicle</h4>
<p>Search vehicle by plate number</p>
</a>
<a href="reports.php" class="action-card">
<div class="action-icon">
<i class="fas fa-file-alt"></i>
</div>
<h4>Generate Report</h4>
<p>Generate traffic reports</p>
</a>
</div>
</div>
</main>
</div>
<!-- Vehicle Search Modal -->
<div id="vehicleSearchModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<h3><i class="fas fa-search"></i> Search Vehicle</h3>
<button class="modal-close">&times;</button>
</div>
<div class="modal-body">
<form id="searchVehicleForm">
<div class="form-group">
<label for="searchPlate">Plate Number</label>
<input type="text" id="searchPlate" placeholder="Enter plate number">
</div>
<div class="form-group">
<label for="searchOwner">Owner Name</label>
<input type="text" id="searchOwner" placeholder="Enter owner name">
</div>
<button type="submit" class="btn-search">
<i class="fas fa-search"></i> Search
</button>
</form>
<div id="searchResults" class="search-results"></div>
</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js"></script>
<script src="js/main.js"></script>
<script src="js/dashboard.js"></script>
<script src="js/ajax.js"></script>
<script src="js/notifications.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
 // Initialize charts
 initCharts();
// Load initial data
loadNodeStatus();
loadTrafficChart();
// Start real-time updates
startRealTimeUpdates();
// Vehicle search functionality
const searchModal = document.getElementById('vehicleSearchModal');
const searchForm = document.getElementById('searchVehicleForm');
if (searchForm) {
searchForm.addEventListener('submit', function(e) {
e.preventDefault();
const plate = document.getElementById('searchPlate').value;
const owner = document.getElementById('searchOwner').value;
 searchVehicle(plate, owner);
 });
 }
 // Close modal
 document.querySelector('.modal-close').addEventListener('click', function() {
searchModal.style.display = 'none';
});
// Close modal when clicking outside
window.addEventListener('click', function(e) {
if (e.target === searchModal) {
searchModal.style.display = 'none';
}
});
});
function searchVehicle(plate, owner) {
const resultsDiv = document.getElementById('searchResults');
resultsDiv.innerHTML = '<div class="loading">Searching...</div>';
fetch(`api/search_vehicle.php?plate=${encodeURIComponent(plate)}&owner=${encodeURIComponent(owner)}`)
.then(response => response.json())
 .then(data => {
 if (data.success && data.vehicles.length > 0) {
 let html = '<div class="results-list">';
data.vehicles.forEach(vehicle => {
html += `
<div class="result-item">
<h4>${vehicle.plate_number}</h4>
<p>${vehicle.type_name} • ${vehicle.model || 'Unknown'} • ${vehicle.color || 'Unknown'}</p>
<p><i class="fas fa-user"></i> ${vehicle.full_name || 'Unknown Owner'}</p>
<a href="vehicles.php?id=${vehicle.id}" class="btn-view">View Details</a>
</div>
`;
 });
 html += '</div>';
 resultsDiv.innerHTML = html;
 } else {
resultsDiv.innerHTML = '<div class="no-results">No vehicles found</div>';
}
})
.catch(error => {
resultsDiv.innerHTML = '<div class="error">Search error</div>';
 });
}
 function openVehicleSearch() {
document.getElementById('vehicleSearchModal').style.display = 'block';
}
</script>
</body>
</html>