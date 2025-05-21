<?php
// filepath: sections/giftcode.php
?>
<h2 class="section-title">Gift Code</h2>

<!-- Form Claim Gift Code -->
<div class="card">
    <h3>Claim Gift Code</h3>
    <form id="claim-gift-code-form">
        <div class="form-group">
            <label for="charid">Select Character</label>
            <select id="charid" name="charid" class="form-control" required>
                <option value="">-- Select Character --</option>
                <?php foreach ($characters as $character): ?>
                    <option value="<?php echo $character['id']; ?>">
                        <?php echo $character['name']; ?> (Level: <?php echo $character['rolelv']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Claim</button>
    </form>
</div>

<script>
document.getElementById('claim-gift-code-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'claim_gift_code');

    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Gift code claimed successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    });
});
</script>