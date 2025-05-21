<?php
// filepath: sections/donation_history.php
?>
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
                <?php if (count($donations) > 0): ?>
                    <?php foreach ($donations as $donation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($donation['transaction_date']); ?></td>
                            <td><?php echo htmlspecialchars($donation['transaction_id']); ?></td>
                            <td><?php echo number_format($donation['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">No donations yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>