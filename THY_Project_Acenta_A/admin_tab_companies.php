<div id="tab-companies" class="tab-content <?php echo $activeTab === 'companies' ? 'active' : ''; ?>">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #232b38;"><i class="fas fa-briefcase"></i> Agency Network Management</h2>
        <button onclick="document.getElementById('add-company-form').style.display='block'" style="background: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="fas fa-plus"></i> Add New Agency
        </button>
    </div>

    <div id="add-company-form" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h3 style="margin-top: 0; color: #333;"><i class="fas fa-building"></i> Register New Agency</h3>
        <form action="admin_dashboard.php?tab=companies" method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="add_company">
            
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Company Name</label>
                <input type="text" name="company_name" required placeholder="e.g. Acenta D Hava Yolları" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;">Agency Code</label>
                <input type="text" name="agency_code" required placeholder="e.g. ACENTA_D" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-transform: uppercase;">
            </div>

            <div>
                <button type="submit" style="background: #c8102e; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;"><i class="fas fa-save"></i> Save</button>
                <button type="button" onclick="document.getElementById('add-company-form').style.display='none'" style="background: #6c757d; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer;">Cancel</button>
            </div>
        </form>
    </div>

    <?php
    // GÜNCELLENMİŞ AKILLI SQL SORGUSU (Ciro Hesaplamalı ve Ciroya Göre Sıralı)
    $sqlCompanies = "
        SELECT 
            C.CompanyID, 
            C.CompanyName, 
            C.AgencyCode, 
            C.IsActive,
            (SELECT COUNT(*) FROM Reservation_Table R WHERE R.CompanyID = C.CompanyID) as TotalReservations,
            (SELECT COUNT(T.TicketID) 
             FROM Tickets_Table T 
             INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
             WHERE R.CompanyID = C.CompanyID AND (T.TicketStatus IS NULL OR T.TicketStatus <> 'Cancelled')) as TotalTickets,
            
            -- GERÇEK CİRO HESAPLAMASI (Toplam Satış - İade Edilen Biletler)
            (
                (SELECT ISNULL(SUM(R2.TotalCost), 0) 
                 FROM Reservation_Table R2 
                 WHERE R2.CompanyID = C.CompanyID AND R2.PaymentStatus = 'Completed')
                -
                (SELECT ISNULL(SUM(T3.TicketPrice), 0) 
                 FROM Tickets_Table T3 
                 INNER JOIN Reservation_Table R3 ON T3.ReservationID = R3.ReservationID 
                 WHERE R3.CompanyID = C.CompanyID AND R3.PaymentStatus = 'Completed' AND T3.TicketStatus = 'Cancelled')
            ) as TotalRevenue

        FROM Companies_Table C
        ORDER BY TotalRevenue DESC, C.CompanyID DESC
    ";
    
    $stmtCompanies = sqlsrv_query($conn, $sqlCompanies);
    $companiesArray = [];
    $totalNetworkRevenue = 0;
    $totalActiveAgencies = 0;

    if ($stmtCompanies !== false) {
        while ($c = sqlsrv_fetch_array($stmtCompanies, SQLSRV_FETCH_ASSOC)) {
            $companiesArray[] = $c;
            $totalNetworkRevenue += $c['TotalRevenue'];
            if ($c['IsActive']) $totalActiveAgencies++;
        }
    }
    
    // En iyi acentayı bul (Eğer ciro > 0 ise dizinin ilk elemanı her zaman en iyisidir çünkü ORDER BY DESC kullandık)
    $topAgency = (!empty($companiesArray) && $companiesArray[0]['TotalRevenue'] > 0) ? $companiesArray[0] : null;
    ?>

    <!-- ADMIN B2B DASHBOARD KARTLARI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px; text-transform: uppercase;"><i class="fas fa-network-wired"></i> Active Agencies</div>
            <div style="font-size: 28px; font-weight: bold;"><?php echo $totalActiveAgencies; ?></div>
        </div>
        
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px; text-transform: uppercase;"><i class="fas fa-coins"></i> Total Network Revenue</div>
            <div style="font-size: 28px; font-weight: bold;">₺<?php echo number_format($totalNetworkRevenue, 0); ?></div>
        </div>

        <div style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative; overflow: hidden;">
            <i class="fas fa-trophy" style="position: absolute; right: -15px; bottom: -15px; font-size: 80px; opacity: 0.2;"></i>
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px; text-transform: uppercase; position: relative; z-index: 1;"><i class="fas fa-star"></i> Top Performing Agency</div>
            <div style="font-size: 20px; font-weight: bold; position: relative; z-index: 1; margin-bottom: 5px;">
                <?php echo $topAgency ? htmlspecialchars($topAgency['CompanyName']) : 'N/A'; ?>
            </div>
            <?php if($topAgency): ?>
                <div style="font-size: 14px; position: relative; z-index: 1;">₺<?php echo number_format($topAgency['TotalRevenue'], 0); ?> generated</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ŞİRKETLER TABLOSU -->
    <div style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <table class="admin-table" style="width: 100%; border-collapse: collapse; background: white;">
            <thead>
                <tr style="background: #232b38; color: white;">
                    <th style="padding: 15px 12px; text-align: left;">ID</th>
                    <th style="padding: 15px 12px; text-align: left;">Company Name</th>
                    <th style="padding: 15px 12px; text-align: left;">Agency Code</th>
                    <th style="padding: 15px 12px; text-align: center;">Reservations</th>
                    <th style="padding: 15px 12px; text-align: center;">Tickets Sold</th>
                    <th style="padding: 15px 12px; text-align: right;">Total Revenue</th>
                    <th style="padding: 15px 12px; text-align: center;">Status</th>
                    <th style="padding: 15px 12px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($companiesArray)) {
                    echo "<tr><td colspan='8' style='text-align:center; padding:30px; color:#777;'><i class='fas fa-folder-open' style='font-size: 24px; color: #ccc; display: block; margin-bottom: 10px;'></i> No companies registered yet.</td></tr>";
                } else {
                    $rank = 0;
                    foreach ($companiesArray as $c) {
                        $rank++;
                        $isActive = $c['IsActive'] ? true : false;
                        $isTop = ($rank === 1 && $c['TotalRevenue'] > 0); // En çok ciro yapan
                        ?>
                        <tr style="border-bottom: 1px solid #eee; <?php echo $isTop ? 'background: #fffdf5;' : ''; ?>">
                            <td style="padding: 12px; color: #666;"><?php echo $c['CompanyID']; ?></td>
                            
                            <td style="padding: 12px;">
                                <strong style="color: #333; font-size: 15px;"><?php echo htmlspecialchars($c['CompanyName']); ?></strong>
                                <?php if($isTop): ?>
                                    <span title="Top Seller" style="margin-left: 8px; color: #ffc107; font-size: 14px;"><i class="fas fa-crown"></i></span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="padding: 12px;">
                                <span style="background: #eef2f6; padding: 4px 8px; border-radius: 4px; font-family: monospace; color: #232b38; font-weight: bold; border: 1px solid #dce4ec;">
                                    <?php echo htmlspecialchars($c['AgencyCode']); ?>
                                </span>
                            </td>
                            
                            <td style="padding: 12px; text-align: center; font-weight: bold; color: #6c757d;">
                                <?php echo $c['TotalReservations']; ?>
                            </td>
                            
                            <td style="padding: 12px; text-align: center;">
                                <span style="background: #e2e3ff; color: #3b3f9f; padding: 3px 8px; border-radius: 12px; font-weight: bold; font-size: 12px;">
                                    <?php echo $c['TotalTickets']; ?> Tickets
                                </span>
                            </td>
                            
                            <td style="padding: 12px; text-align: right; font-weight: bold; color: #28a745; font-size: 15px;">
                                ₺<?php echo number_format($c['TotalRevenue'], 2); ?>
                            </td>
                            
                            <td style="padding: 12px; text-align: center;">
                                <?php if ($isActive): ?>
                                    <span style="background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 12px; font-size: 11px; font-weight: bold;"><i class="fas fa-check"></i> Active</span>
                                <?php else: ?>
                                    <span style="background: #f8d7da; color: #721c24; padding: 5px 10px; border-radius: 12px; font-size: 11px; font-weight: bold;"><i class="fas fa-times"></i> Suspended</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="padding: 12px; text-align: center;">
                                <form action="admin_dashboard.php?tab=companies" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_company_status">
                                    <input type="hidden" name="company_id" value="<?php echo $c['CompanyID']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $c['IsActive']; ?>">
                                    <?php if ($isActive): ?>
                                        <button type="submit" onclick="return confirm('Suspend this company? They will not be able to sell new tickets.');" style="background: white; color: #dc3545; border: 1px solid #dc3545; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; transition: 0.2s;" onmouseover="this.style.background='#dc3545'; this.style.color='white';" onmouseout="this.style.background='white'; this.style.color='#dc3545';">
                                            <i class="fas fa-ban"></i> Suspend
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" style="background: #28a745; color: white; border: none; padding: 5px 9px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold;">
                                            <i class="fas fa-check-circle"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>