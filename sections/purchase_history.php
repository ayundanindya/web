<?php
// filepath: sections/purchase_history.php
?>
<h2 class="section-title">Purchase History</h2>

<div class="card">
    <h3>Shop Transaction History</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Character</th>
                    <th>Transaction ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($purchases) > 0): ?>
                    <?php foreach ($purchases as $purchase): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($purchase['purchase_time']); ?></td>
                            <td><?php echo htmlspecialchars($purchase['product_name']); ?></td>
                            <td><?php echo number_format($purchase['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($purchase['charid']); ?></td>
                            <td><?php echo htmlspecialchars($purchase['transaction_id']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No transactions yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>