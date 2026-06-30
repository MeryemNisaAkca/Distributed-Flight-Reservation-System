<?php
//This is the part where aircraft are managed (added, deleted, updated) and the "multiple of 6" rule is checked on both the server and client sides.
// Handle Planes-related form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Add new Plane
    if ($_POST['action'] === 'add_plane') {
        $model = trim($_POST['model'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        
        if (!empty($model) && $capacity > 0) {
            // Validate that capacity is a multiple of 6
            if ($capacity % 6 !== 0) {
                $message = "<div style='color:red; margin-bottom:15px; padding:10px; background:#fff3cd; border:2px solid #ffc107; border-radius:5px;'><i class='fas fa-exclamation-triangle'></i> <strong>Error:</strong> Seat capacity must be a multiple of 6 (e.g., 6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84, 90, 96, 102, 108, 114, 120, 126, 132, 138, 144, 150, 156, 162, 168, 174, 180, etc.).</div>";
                $activeTab = 'planes';
            } else {
                $sql = "INSERT INTO Planes_Table (Model, SeatCapacity) VALUES (?, ?)";
                $stmt = sqlsrv_query($conn, $sql, array($model, $capacity));
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    error_log("Plane add failed: " . print_r($errors, true));
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Plane add failed. Please try again.</div>";
                    $activeTab = 'planes';
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Plane added successfully.</div>";
                    $activeTab = 'planes';
                }
            }
        }
    }
    
    // Update Plane
    if ($_POST['action'] === 'update_plane') {
        $planeID = (int)($_POST['plane_id'] ?? 0);
        $model = trim($_POST['model'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        
        if ($planeID > 0 && !empty($model) && $capacity > 0) {
            // Validate that capacity is a multiple of 6
            if ($capacity % 6 !== 0) {
                $message = "<div style='color:red; margin-bottom:15px; padding:10px; background:#fff3cd; border:2px solid #ffc107; border-radius:5px;'><i class='fas fa-exclamation-triangle'></i> <strong>Error:</strong> Seat capacity must be a multiple of 6 (e.g., 6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84, 90, 96, 102, 108, 114, 120, 126, 132, 138, 144, 150, 156, 162, 168, 174, 180, etc.).</div>";
                $activeTab = 'planes';
            } else {
                $sql = "UPDATE Planes_Table SET Model = ?, SeatCapacity = ? WHERE PlaneID = ?";
                $stmt = sqlsrv_query($conn, $sql, array($model, $capacity, $planeID));
                if ($stmt === false) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Plane update failed: " . print_r(sqlsrv_errors(), true) . "</div>";
                    $activeTab = 'planes';
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Plane updated successfully.</div>";
                    $activeTab = 'planes';
                }
            }
        }
    }
    
    // Delete Plane
    if ($_POST['action'] === 'delete_plane') {
        $planeID = (int)($_POST['plane_id'] ?? 0);
        if ($planeID > 0) {
            $sql = "DELETE FROM Planes_Table WHERE PlaneID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($planeID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Plane delete failed: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Plane delete failed. Please try again.</div>";
                $activeTab = 'planes';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Plane deleted successfully.</div>";
                $activeTab = 'planes';
            }
        }
    }
}

// Fetch Planes data for display (refresh after POST)
$sqlPlanes = "SELECT * FROM Planes_Table ORDER BY Model";
$stmtPlanes = sqlsrv_query($conn, $sqlPlanes);
$planesArray = [];
if ($stmtPlanes) {
    while($p = sqlsrv_fetch_array($stmtPlanes, SQLSRV_FETCH_ASSOC)) {
        $planesArray[] = $p;
    }
}
?>

<!-- PLANES TAB CONTENT -->
<div id="tab-planes" class="tab-content <?php echo $activeTab === 'planes' ? 'active' : ''; ?>">
    <h2><i class="fas fa-plane-departure"></i> Plane Management</h2>
    
    <div class="admin-form">
        <h3>Add New Plane</h3>
        <form method="POST" action="admin_dashboard.php?tab=planes" id="addPlaneForm" onsubmit="return validateSeatCapacity(this);">
            <input type="hidden" name="action" value="add_plane">
            <div class="form-row">
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" required placeholder="Boeing 737">
                </div>
                <div class="form-group">
                    <label>Seat Capacity <span style="color:#856404; font-size:12px;">(must be multiple of 6)</span></label>
                    <input type="number" name="capacity" id="add_capacity" required min="6" step="6" placeholder="180" oninput="validateCapacityInput(this)">
                    <small id="capacity_hint" style="display:none; color:#856404; font-size:11px; margin-top:5px;">
                        <i class="fas fa-info-circle"></i> Next valid value: <span id="next_valid"></span>
                    </small>
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Add Plane</button>
        </form>
    </div>

    <h3>All Planes</h3>
    <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
        <?php if(!empty($planesArray)): ?>
            <?php foreach($planesArray as $p): 
                // Generate color based on plane model (consistent colors)
                $modelHash = crc32($p['Model']);
                $colors = [
                    ['bg' => '#e3f2fd', 'border' => '#2196f3', 'text' => '#1976d2'],
                    ['bg' => '#fff3e0', 'border' => '#ff9800', 'text' => '#f57c00'],
                    ['bg' => '#e8f5e9', 'border' => '#4caf50', 'text' => '#388e3c'],
                    ['bg' => '#fce4ec', 'border' => '#e91e63', 'text' => '#c2185b'],
                    ['bg' => '#f3e5f5', 'border' => '#9c27b0', 'text' => '#7b1fa2']
                ];
                $planeConfig = $colors[abs($modelHash) % count($colors)];
            ?>
                <div style="background: white; border: 2px solid <?php echo $planeConfig['border']; ?>; border-left: 4px solid <?php echo $planeConfig['border']; ?>; border-radius: 8px; padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; position: relative;" 
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    
                    <!-- Plane Header -->
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div style="background: linear-gradient(135deg, <?php echo $planeConfig['border']; ?> 0%, <?php echo $planeConfig['text']; ?> 100%); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); flex-shrink: 0;">
                            <i class="fas fa-plane"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <h3 style="margin: 0; color: #232b38; font-size: 16px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($p['Model']); ?>
                            </h3>
                            <div style="color: #666; font-size: 12px; margin-top: 3px; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-id-card" style="color: <?php echo $planeConfig['text']; ?>; font-size: 10px;"></i>
                                <span>ID: #<?php echo $p['PlaneID']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Plane Details -->
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border-left: 3px solid <?php echo $planeConfig['border']; ?>; margin-bottom: 12px;">
                        <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 5px;">
                            <i class="fas fa-chair"></i> Seat Capacity
                        </div>
                        <div style="font-size: 20px; font-weight: bold; color: <?php echo $planeConfig['text']; ?>;">
                            <?php echo $p['SeatCapacity']; ?> <span style="font-size: 12px; color: #666; font-weight: normal;">seats</span>
                        </div>
                        <div style="font-size: 10px; color: #856404; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> Multiple of 6
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; padding-top: 10px; border-top: 1px solid #eee;">
                        <form method="POST" action="admin_dashboard.php?tab=planes" id="edit_plane_<?php echo $p['PlaneID']; ?>" style="display:none; flex: 1;" onsubmit="return validateSeatCapacity(this);">
                            <input type="hidden" name="action" value="update_plane">
                            <input type="hidden" name="plane_id" value="<?php echo $p['PlaneID']; ?>">
                            <div style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                                <input type="text" name="model" value="<?php echo htmlspecialchars($p['Model']); ?>" style="padding:5px; flex:1; min-width:120px; font-size:11px; border-radius:4px; border:1px solid #ddd;" required>
                                <input type="number" name="capacity" id="edit_capacity_<?php echo $p['PlaneID']; ?>" value="<?php echo $p['SeatCapacity']; ?>" min="6" step="6" style="width:70px; padding:5px; font-size:11px; border-radius:4px; border:1px solid #ddd;" required oninput="validateCapacityInput(this)">
                                <span style="font-size:10px; color:#856404;">(×6)</span>
                                <button type="submit" class="btn-submit" style="padding:5px 10px; font-size:11px; border-radius:4px;"><i class="fas fa-save"></i></button>
                                <button type="button" onclick="cancelEdit('plane', <?php echo $p['PlaneID']; ?>)" class="btn-delete" style="padding:5px 10px; font-size:11px; border-radius:4px;"><i class="fas fa-times"></i></button>
                            </div>
                        </form>
                        <div id="view_plane_<?php echo $p['PlaneID']; ?>" style="display: flex; gap: 6px; flex: 1;">
                            <button onclick="showEdit('plane', <?php echo $p['PlaneID']; ?>)" class="btn-edit" style="padding: 5px 10px; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; flex: 1;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" action="admin_dashboard.php?tab=planes" style="display:inline;" onsubmit="return confirm('Delete this plane?');">
                                <input type="hidden" name="action" value="delete_plane">
                                <input type="hidden" name="plane_id" value="<?php echo $p['PlaneID']; ?>">
                                <button type="submit" class="btn-delete" style="padding: 5px 10px; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 12px; border: 2px dashed #ddd; grid-column: 1 / -1;">
                <i class="fas fa-plane" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                <p style="color: #999; font-size: 18px; margin: 0;">No planes found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function validateSeatCapacity(form) {
    const capacityInput = form.querySelector('input[name="capacity"]');
    const capacity = parseInt(capacityInput.value);
    
    if (isNaN(capacity) || capacity <= 0) {
        alert('Please enter a valid seat capacity.');
        capacityInput.focus();
        return false;
    }
    
    if (capacity % 6 !== 0) {
        const nextValid = Math.ceil(capacity / 6) * 6;
        const prevValid = Math.floor(capacity / 6) * 6;
        let suggestion = '';
        if (prevValid >= 6) {
            suggestion = `\n\nSuggested values: ${prevValid} or ${nextValid}`;
        } else {
            suggestion = `\n\nSuggested value: ${nextValid}`;
        }
        alert('Seat capacity must be a multiple of 6 (e.g., 6, 12, 18, 24, 30, 36, 42, 48, 54, 60, 66, 72, 78, 84, 90, 96, 102, 108, 114, 120, 126, 132, 138, 144, 150, 156, 162, 168, 174, 180, etc.).' + suggestion);
        capacityInput.focus();
        capacityInput.style.borderColor = '#ff0000';
        capacityInput.style.borderWidth = '2px';
        return false;
    }
    
    capacityInput.style.borderColor = '';
    capacityInput.style.borderWidth = '';
    return true;
}

function validateCapacityInput(input) {
    const capacity = parseInt(input.value);
    
    if (isNaN(capacity) || capacity <= 0) {
        input.style.borderColor = '#ff0000';
        input.style.borderWidth = '2px';
        const hint = input.parentElement.querySelector('#capacity_hint') || input.parentElement.parentElement.querySelector('#capacity_hint');
        if (hint) {
            hint.style.display = 'none';
        }
        return;
    }
    
    if (capacity % 6 !== 0) {
        input.style.borderColor = '#ffc107';
        input.style.borderWidth = '2px';
        const nextValid = Math.ceil(capacity / 6) * 6;
        const prevValid = Math.floor(capacity / 6) * 6;
        let hintText = '';
        if (prevValid >= 6) {
            hintText = `Next valid values: ${prevValid} or ${nextValid}`;
        } else {
            hintText = `Next valid value: ${nextValid}`;
        }
        
        let hint = input.parentElement.querySelector('#capacity_hint') || input.parentElement.parentElement.querySelector('#capacity_hint');
        if (!hint) {
            hint = document.createElement('small');
            hint.id = 'capacity_hint';
            hint.style.cssText = 'display:block; color:#856404; font-size:11px; margin-top:5px;';
            hint.innerHTML = '<i class="fas fa-info-circle"></i> <span id="next_valid"></span>';
            input.parentElement.appendChild(hint);
        }
        document.getElementById('next_valid').textContent = hintText;
        hint.style.display = 'block';
    } else {
        input.style.borderColor = '#28a745';
        input.style.borderWidth = '1px';
        const hint = input.parentElement.querySelector('#capacity_hint') || input.parentElement.parentElement.querySelector('#capacity_hint');
        if (hint) {
            hint.style.display = 'none';
        }
    }
}
</script>
