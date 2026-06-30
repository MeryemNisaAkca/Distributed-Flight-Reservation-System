<div id="tab-companies" class="tab-content <?php echo $activeTab === 'companies' ? 'active' : ''; ?>">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #232b38;"><i class="fas fa-briefcase"></i> Company Management</h2>
        <button onclick="document.getElementById('add-company-form').style.display='block'" style="background: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-weight: bold;">
            <i class="fas fa-plus"></i> Add New Company
        </button>
    </div>

    <div id="add-company-form" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd;">
        <h3 style="margin-top: 0; color: #333;">Register New Company</h3>
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
                <button type="submit" style="background: #c8102e; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">Save</button>
                <button type="button" onclick="document.getElementById('add-company-form').style.display='none'" style="background: #6c757d; color: white; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer;">Cancel</button>
            </div>
        </form>
    </div>

    <table class="admin-table" style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <thead>
            <tr style="background: #232b38; color: white;">
                <th style="padding: 12px; text-align: left;">ID</th>
                <th style="padding: 12px; text-align: left;">Company Name</th>
                <th style="padding: 12px; text-align: left;">Agency Code</th>
                <!-- Başlık Uçuşlardan Rezervasyonlara Çevrildi -->
                <th style="padding: 12px; text-align: center;">Total Reservations</th>
                <th style="padding: 12px; text-align: center;">Tickets Sold</th>
                <th style="padding: 12px; text-align: center;">Status</th>
                <th style="padding: 12px; text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // YENİ MİMARİYE GÖRE GÜNCELLENMİŞ AKILLI SQL SORGUSU
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
            
            if ($stmtCompanies === false) {
                echo "<tr><td colspan='7' style='text-align:center; color:red;'>Error loading companies.</td></tr>";
            } else {
                $hasCompanies = false;
                while ($c = sqlsrv_fetch_array($stmtCompanies, SQLSRV_FETCH_ASSOC)) {
                    $hasCompanies = true;
                    $isActive = $c['IsActive'] ? true : false;
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px;"><?php echo $c['CompanyID']; ?></td>
                        <td style="padding: 12px;"><strong><?php echo htmlspecialchars($c['CompanyName']); ?></strong></td>
                        <td style="padding: 12px;">
                            <span style="background: #eef2f6; padding: 4px 8px; border-radius: 4px; font-family: monospace; color: #232b38;">
                                <?php echo htmlspecialchars($c['AgencyCode']); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center; font-weight: bold; color: #0056b3;">
                            <?php echo $c['TotalReservations']; ?>
                        </td>
                        <td style="padding: 12px; text-align: center; font-weight: bold; color: #28a745;">
                            <?php echo $c['TotalTickets']; ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?php if ($isActive): ?>
                                <span style="background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;"><i class="fas fa-check"></i> Active</span>
                            <?php else: ?>
                                <span style="background: #f8d7da; color: #721c24; padding: 5px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;"><i class="fas fa-times"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <form action="admin_dashboard.php?tab=companies" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_company_status">
                                <input type="hidden" name="company_id" value="<?php echo $c['CompanyID']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $c['IsActive']; ?>">
                                <?php if ($isActive): ?>
                                    <!-- Uyarı metni güncellendi -->
                                    <button type="submit" onclick="return confirm('Suspend this company? They will not be able to sell new tickets.');" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                        <i class="fas fa-ban"></i> Suspend
                                    </button>
                                <?php else: ?>
                                    <button type="submit" style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                        <i class="fas fa-check-circle"></i> Activate
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php
                }
                if (!$hasCompanies) {
                    echo "<tr><td colspan='7' style='text-align:center; padding:20px; color:#777;'>No companies registered yet.</td></tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>