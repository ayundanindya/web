<!-- Donation History Section -->
<h2 class="section-title">Donation History</h2>

<div class="card">
    <h3>Donation History</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Transaction ID</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Ambil data donasi dari database
                $stmt = $conn->prepare("SELECT purchase_time AS transaction_date, transaction_id, amount FROM purchase_history WHERE accid = ? ORDER BY purchase_time DESC");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0):
                    while ($donation = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($donation['transaction_date']); ?></td>
                            <td><?php echo htmlspecialchars($donation['transaction_id']); ?></td>
                            <td><?php echo number_format($donation['amount']); ?></td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">No donations yet</td>
                    </tr>
                <?php endif;
                $stmt->close();
                ?>
            </tbody>
        </table>
    </div>
</div>