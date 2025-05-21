<?php
// File: ranking.php
// Halaman untuk menampilkan ranking pemain dan guild

// Mendapatkan kategori ranking dari query parameter
$rankType = isset($_GET['type']) ? $_GET['type'] : 'level';
$jobFilter = isset($_GET['job']) ? (int)$_GET['job'] : 0;

// Data job berdasarkan file yang diberikan
$jobClasses = [
    1 => "Novice",
    11 => "Swordman",
    12 => "Knight",
    13 => "LordKnight",
    14 => "RuneKnight",
    21 => "Magician",
    22 => "Wizard",
    23 => "HighWizard",
    24 => "Warlock",
    31 => "Thief",
    32 => "Assassin",
    33 => "AssassinCross",
    34 => "GuillotineCross",
    41 => "Archer",
    42 => "Hunter",
    43 => "Sniper",
    44 => "Ranger",
    51 => "Acolyte",
    52 => "Priest",
    53 => "HighPriest",
    54 => "Archbishop",
    61 => "Merchant",
    62 => "Blacksmith",
    63 => "Whitesmith",
    64 => "Mechanic",
    72 => "Crusader",
    73 => "Paladin",
    74 => "RoyalGuard",
    82 => "Sage",
    83 => "Professor",
    84 => "Sorcerer",
    92 => "Rogue",
    93 => "Stalker",
    94 => "ShadowChaser",
    102 => "Bard",
    103 => "Clown",
    104 => "Minstrel",
    112 => "Dancer",
    113 => "Gypsy",
    114 => "Wanderer",
    122 => "Monk",
    123 => "Champion",
    124 => "Shura",
    132 => "Alchemist",
    133 => "Creator",
    134 => "Genetic",
    500 => "RiskSkill"
];

// Pengelompokan job untuk filter
$jobGroups = [
    "All" => 0,
    "Swordman" => 1,
    "Magician" => 2,
    "Thief" => 3,
    "Archer" => 4, 
    "Acolyte" => 5,
    "Merchant" => 6
];

// Judul dan query berdasarkan tipe ranking
if ($rankType == 'wealth') {
    $rankTitle = "Wealth Ranking";
    $query = "SELECT c.name, c.rolelv, c.profession, c.silver, c.guildid FROM charbase c";
    if ($jobFilter > 0) {
        // Filter berdasarkan kelompok job
        $query .= " WHERE FLOOR(c.profession/10) = $jobFilter OR (c.profession > 0 AND c.profession < 10 AND $jobFilter = 1)";
    }
    $query .= " ORDER BY silver DESC LIMIT 100";
    $resultType = "player";
} else if ($rankType == 'guild') {
    $rankTitle = "Guild Ranking";
    $query = "SELECT id, name, lv, createtime FROM guild ORDER BY lv DESC, id ASC LIMIT 100";
    $resultType = "guild";
} else {
    $rankTitle = "Level Ranking";
    $query = "SELECT c.name, c.rolelv, c.profession, c.silver, c.guildid FROM charbase c";
    if ($jobFilter > 0) {
        // Filter berdasarkan kelompok job
        $query .= " WHERE FLOOR(c.profession/10) = $jobFilter OR (c.profession > 0 AND c.profession < 10 AND $jobFilter = 1)";
    }
    $query .= " ORDER BY rolelv DESC, roleexp DESC LIMIT 100";
    $resultType = "player";
}

// Eksekusi query menggunakan koneksi yang sudah ada dari database.php
$result = $conn_ro->query($query);

// Mengambil data guild dari database untuk referensi
$guildQuery = "SELECT id, name, lv, createtime FROM guild";
$guildResult = $conn_ro->query($guildQuery);

// Membuat array guild untuk referensi cepat
$guilds = [];
if ($guildResult && $guildResult->num_rows > 0) {
    while ($guildRow = $guildResult->fetch_assoc()) {
        $guilds[$guildRow['id']] = [
            'name' => $guildRow['name'],
            'level' => $guildRow['lv'],
            'createtime' => $guildRow['createtime']
        ];
    }
}

// Menghitung jumlah anggota untuk setiap guild
// Ini lebih efisien daripada menanyakan database untuk setiap guild secara terpisah
$memberCountQuery = "SELECT guildid, COUNT(*) as count FROM charbase WHERE guildid > 0 GROUP BY guildid";
$memberCountResult = $conn_ro->query($memberCountQuery);

$guildMemberCounts = [];
if ($memberCountResult && $memberCountResult->num_rows > 0) {
    while ($row = $memberCountResult->fetch_assoc()) {
        $guildMemberCounts[$row['guildid']] = $row['count'];
    }
}

// Fungsi untuk format nilai silver ke format yang lebih mudah dibaca
function formatSilver($silver) {
    if ($silver >= 1000000000) {
        return round($silver / 1000000000, 2) . ' B';
    } elseif ($silver >= 1000000) {
        return round($silver / 1000000, 2) . ' M';
    } elseif ($silver >= 1000) {
        return round($silver / 1000, 2) . ' K';
    }
    return $silver;
}

// Fungsi untuk mengkonversi timestamp ke format yang lebih mudah dibaca
function formatTimestamp($timestamp) {
    return date('Y-m-d', $timestamp);
}

// Fungsi untuk mendapatkan nama job berdasarkan profession ID
function getProfessionName($professionId, $jobClasses) {
    return isset($jobClasses[$professionId]) ? $jobClasses[$professionId] : "Unknown";
}
?>

<div class="content-block">
  <h2>Rankings</h2>
  <p>Explore the top players and guilds in V-Ragnarok Mobile Online</p>
  
  <div class="ranking-filters">
    <a href="?page=ranking&type=level&job=<?php echo $jobFilter; ?>" class="filter-btn <?php echo ($rankType == 'level') ? 'active' : ''; ?>">Level Ranking</a>
    <a href="?page=ranking&type=wealth&job=<?php echo $jobFilter; ?>" class="filter-btn <?php echo ($rankType == 'wealth') ? 'active' : ''; ?>">Zeny Ranking</a>
    <a href="?page=ranking&type=guild" class="filter-btn <?php echo ($rankType == 'guild') ? 'active' : ''; ?>">Guild Ranking</a>
  </div>
  
  <?php if ($rankType != 'guild'): ?>
  <div class="job-filters">
    <span>Class Filter:</span>
    <?php foreach ($jobGroups as $groupName => $groupId): ?>
      <a href="?page=ranking&type=<?php echo $rankType; ?>&job=<?php echo $groupId; ?>" 
         class="job-filter <?php echo ($jobFilter == $groupId) ? 'active' : ''; ?>">
        <?php echo $groupName; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  
  <div class="ranking-table">
    <h3><?php echo $rankTitle; ?> 
      <?php if ($jobFilter > 0 && $rankType != 'guild'): ?>
        - <?php echo array_search($jobFilter, $jobGroups); ?> Class
      <?php endif; ?>
    </h3>
    
    <?php if ($resultType == "player"): ?>
    <!-- Player Rankings Table -->
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Character</th>
          <th>Class</th>
          <th>Level</th>
          <?php if ($rankType == 'wealth'): ?>
          <th>Zeny</th>
          <?php endif; ?>
          <th>Guild</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($result && $result->num_rows > 0) {
            $rank = 1;
            while ($row = $result->fetch_assoc()) {
                // Get guild information
                $guildInfo = "None";
                if ($row['guildid'] > 0 && isset($guilds[$row['guildid']])) {
                    $guild = $guilds[$row['guildid']];
                    $guildInfo = '<div class="guild-info">' . 
                                 '<span class="guild-name">' . htmlspecialchars($guild['name']) . '</span>' .
                                 '<span class="guild-level">Lv. ' . $guild['level'] . '</span>' .
                                 '</div>';
                }
                
                // Get job class name
                $className = getProfessionName($row['profession'], $jobClasses);
                
                // Adding class for styling row based on job type
                $jobType = "";
                $profession = $row['profession'];
                
                if ($profession >= 11 && $profession <= 14) $jobType = "swordsman";
                else if ($profession >= 21 && $profession <= 24) $jobType = "magician";
                else if ($profession >= 31 && $profession <= 34) $jobType = "thief";
                else if (($profession >= 41 && $profession <= 44) || 
                         ($profession >= 102 && $profession <= 114)) $jobType = "archer";
                else if (($profession >= 51 && $profession <= 54) || 
                         ($profession >= 122 && $profession <= 124)) $jobType = "acolyte";
                else if (($profession >= 61 && $profession <= 64) || 
                         ($profession >= 132 && $profession <= 134)) $jobType = "merchant";
                else if ($profession >= 72 && $profession <= 74) $jobType = "crusader";
                else if ($profession >= 82 && $profession <= 84) $jobType = "sage";
                else if ($profession >= 92 && $profession <= 94) $jobType = "rogue";
                
                echo '<tr class="job-' . $jobType . '">';
                echo '<td>' . $rank . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . $className . '</td>';
                echo '<td>' . $row['rolelv'] . '</td>';
                if ($rankType == 'wealth') {
                    echo '<td>' . formatSilver($row['silver']) . '</td>';
                }
                echo '<td>' . $guildInfo . '</td>';
                echo '</tr>';
                
                $rank++;
            }
        } else {
            echo '<tr><td colspan="' . ($rankType == 'wealth' ? 6 : 5) . '" class="no-data">No player data available.</td></tr>';
        }
        ?>
      </tbody>
    </table>
    
    <?php else: ?>
    <!-- Guild Rankings Table -->
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Guild Name</th>
          <th>Level</th>
          <th>Members</th>
          <th>Create Time</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($result && $result->num_rows > 0) {
            $rank = 1;
            while ($row = $result->fetch_assoc()) {
                // Get member count dari array yang sudah dihitung sebelumnya
                $memberCount = isset($guildMemberCounts[$row['id']]) ? $guildMemberCounts[$row['id']] : 0;
                
                echo '<tr>';
                echo '<td>' . $rank . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . $row['lv'] . '</td>';
                echo '<td>' . $memberCount . '</td>';
                echo '<td>' . formatTimestamp($row['createtime']) . '</td>';
                echo '</tr>';
                
                $rank++;
            }
        } else {
            echo '<tr><td colspan="5" class="no-data">No guild data available.</td></tr>';
        }
        ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<style>
.content-block {
  padding: 20px;
  color: #333;
}

.content-block h2 {
  color: #6a11cb;
  font-size: 28px;
  margin-bottom: 15px;
  text-align: center;
}

.content-block p {
  text-align: center;
  margin-bottom: 30px;
}

.ranking-filters {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-bottom: 20px;
}

.filter-btn {
  background: linear-gradient(145deg, #e6e7e9, #f3f4f6);
  color: #4a5568;
  padding: 10px 20px;
  border-radius: 5px;
  text-decoration: none;
  font-weight: bold;
  transition: all 0.3s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-btn:hover {
  background: linear-gradient(145deg, #d5d6d8, #e3e4e6);
  transform: translateY(-2px);
}

.filter-btn.active {
  background: linear-gradient(145deg, #6a11cb, #8844e0);
  color: white;
  box-shadow: 0 4px 8px rgba(106, 17, 203, 0.3);
}

.job-filters {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  margin-bottom: 30px;
  padding: 10px;
  background: #f0f2f5;
  border-radius: 5px;
}

.job-filters span {
  font-weight: bold;
  margin-right: 10px;
  color: #4a5568;
  display: flex;
  align-items: center;
}

.job-filter {
  background: white;
  color: #4a5568;
  padding: 5px 10px;
  border-radius: 4px;
  text-decoration: none;
  font-size: 0.9rem;
  transition: all 0.3s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.job-filter:hover {
  background: #edf2f7;
  transform: translateY(-1px);
}

.job-filter.active {
  background: #6a11cb;
  color: white;
  box-shadow: 0 2px 5px rgba(106, 17, 203, 0.3);
}

.ranking-table {
  background: #f9fafb;
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  overflow-x: auto;
}

.ranking-table h3 {
  color: #6a11cb;
  text-align: center;
  margin-bottom: 20px;
  font-size: 22px;
}

.ranking-table table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.ranking-table th {
  background: linear-gradient(145deg, #6a11cb, #8844e0);
  color: white;
  padding: 15px;
  text-align: left;
}

.ranking-table td {
  padding: 12px 15px;
  border-bottom: 1px solid #e5e7eb;
}

.ranking-table tr:last-child td {
  border-bottom: none;
}

.ranking-table tr:hover {
  background-color: #f5f5f5;
}

.ranking-table tr:nth-child(even) {
  background-color: #f9f9f9;
}

/* Guild info styling */
.guild-info {
  display: flex;
  flex-direction: column;
}

.guild-name {
  font-weight: bold;
  color: #4a5568;
}

.guild-level {
  font-size: 0.85rem;
  color: #6a11cb;
}

/* Job class styling */
.job-swordsman {
  border-left: 3px solid #ff5252;
}

.job-magician {
  border-left: 3px solid #2196f3;
}

.job-thief {
  border-left: 3px solid #673ab7;
}

.job-archer {
  border-left: 3px solid #4caf50;
}

.job-acolyte {
  border-left: 3px solid #ffeb3b;
}

.job-merchant {
  border-left: 3px solid #ff9800;
}

.job-crusader {
  border-left: 3px solid #8d6e63;
}

.job-sage {
  border-left: 3px solid #00bcd4;
}

.job-rogue {
  border-left: 3px solid #9c27b0;
}

.no-data {
  text-align: center;
  padding: 20px;
  color: #666;
}

@media (max-width: 768px) {
  .ranking-table table {
    min-width: 600px;
  }
  
  .job-filters {
    padding: 10px 5px;
  }
  
  .job-filter {
    padding: 4px 8px;
    font-size: 0.8rem;
  }
}
</style>