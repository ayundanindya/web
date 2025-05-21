<?php
// Pastikan koneksi $conn_ro sudah ada (include jika perlu)
// include 'database.php';

$jobClasses = [
    1 => "Novice", 11 => "Swordman", 12 => "Knight", 13 => "LordKnight", 14 => "RuneKnight",
    21 => "Magician", 22 => "Wizard", 23 => "HighWizard", 24 => "Warlock",
    31 => "Thief", 32 => "Assassin", 33 => "AssassinCross", 34 => "GuillotineCross",
    41 => "Archer", 42 => "Hunter", 43 => "Sniper", 44 => "Ranger",
    51 => "Acolyte", 52 => "Priest", 53 => "HighPriest", 54 => "Archbishop",
    61 => "Merchant", 62 => "Blacksmith", 63 => "Whitesmith", 64 => "Mechanic",
    72 => "Crusader", 73 => "Paladin", 74 => "RoyalGuard",
    82 => "Sage", 83 => "Professor", 84 => "Sorcerer",
    92 => "Rogue", 93 => "Stalker", 94 => "ShadowChaser",
    102 => "Bard", 103 => "Clown", 104 => "Minstrel",
    112 => "Dancer", 113 => "Gypsy", 114 => "Wanderer",
    122 => "Monk", 123 => "Champion", 124 => "Shura",
    132 => "Alchemist", 133 => "Creator", 134 => "Genetic",
    500 => "RiskSkill"
];

// Ambil 5 karakter dengan level tertinggi
$query = "SELECT name, rolelv, profession FROM charbase ORDER BY rolelv DESC, roleexp DESC LIMIT 5";
$result = $conn_ro->query($query);

?>
<div class="top5-widget">
  <h3>🔥 Top 5 Adventurers</h3>
  <table>
    <thead>
      <tr>
        <th>Rank</th>
        <th>Character</th>
        <th>Class</th>
        <th>Level</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if ($result && $result->num_rows > 0) {
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
          $className = isset($jobClasses[$row['profession']]) ? $jobClasses[$row['profession']] : "Unknown";
          echo "<tr>
                  <td style='text-align:center;'>$rank</td>
                  <td>".htmlspecialchars($row['name'])."</td>
                  <td>$className</td>
                  <td style='text-align:center;'>{$row['rolelv']}</td>
                </tr>";
          $rank++;
        }
      } else {
        echo "<tr><td colspan='4' style='text-align:center;'>No data available</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<style>
.top5-widget {
  margin: 0 auto;
  max-width: 450px;
  padding: 20px 10px;
  background: rgba(255,255,255,0.92);
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(106,17,203,0.15);
}
.top5-widget h3 {
  text-align: center;
  color: #6a11cb;
  margin-bottom: 12px;
  font-size: 20px;
}
.top5-widget table {
  width: 100%;
  border-collapse: collapse;
}
.top5-widget th, .top5-widget td {
  padding: 7px 4px;
  border-bottom: 1px solid #e0e0e0;
}
.top5-widget th {
  background: #6a11cb;
  color: #fff;
  font-size: 1em;
}
.top5-widget tr:last-child td {
  border-bottom: none;
}
</style>