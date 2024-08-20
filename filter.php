<?php
require 'database-connect.php';

// Pagination
$itemsPerPage = 102;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

$results = [];
$totalPages = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET parameters
    $searchTerm = $_GET['search'] ?? '';
    $languageCode = $_GET['language'] ?? '';
    $sortOrder = $_GET['sortOrder'] ?? 'viewCountDesc';
    $gamesInput = $_GET['games'] ?? '';
    $useRegex = isset($_GET['useRegex']) ? (bool)$_GET['useRegex'] : false;
    $tagsInput = $_GET['tags'] ?? '';
    $includeAllTags = isset($_GET['includeAllTags']) ? (bool)$_GET['includeAllTags'] : false;
    $minViewers = $_GET['minViewers'] ?? '';
    $maxViewers = $_GET['maxViewers'] ?? '';
    $broadcasterType = $_GET['broadcasterType'] ?? '';

    // Construct WHERE clause based on input
    $games = array_filter(array_map('trim', explode(',', $gamesInput)));
    $tags = array_filter(array_map('trim', explode(',', $tagsInput)));
    $excludeTagsInput = $_GET['excludeTags'] ?? '';
    $excludeTags = array_filter(array_map('trim', explode(',', $excludeTagsInput)));

    $params = [];
    $whereParts = [];

    // Apply filters based on input
    if (!empty($searchTerm)) {
        $whereParts[] = "title LIKE CONCAT('%', ?, '%')";
        $params[] = $searchTerm;
    }
    if (!empty($languageCode)) {
        $whereParts[] = "language = ?";
        $params[] = $languageCode;
    }
    if (!empty($games)) {
        if (isset($_GET['useRegex']) && $_GET['useRegex'] == 'on') {
            foreach ($games as $game) {
                $whereParts[] = "LOWER(game_name) REGEXP LOWER(?)";
                $params[] = $game;
            }
        } else {
            $placeholders = implode(',', array_fill(0, count($games), '?'));
            $whereParts[] = "game_name IN ($placeholders)";
            $params = array_merge($params, $games);
        }
    }
    if (!empty($tags)) {
        if ($includeAllTags) {
            foreach ($tags as $tag) {
                $whereParts[] = "FIND_IN_SET(?, tags) > 0";
                $params[] = $tag;
            }
        } else {
            $tagConditions = implode(' OR ', array_fill(0, count($tags), "FIND_IN_SET(?, tags) > 0"));
            $whereParts[] = "($tagConditions)";
            $params = array_merge($params, $tags);
        }
    }
    if (!empty($excludeTags)) {
        $excludeTagConditions = implode(' AND ', array_fill(0, count($excludeTags), "FIND_IN_SET(?, tags) = 0"));
        $whereParts[] = "($excludeTagConditions)";
        $params = array_merge($params, $excludeTags);
    }
    if ($minViewers !== '') {
        $whereParts[] = "viewcount >= ?";
        $params[] = $minViewers;
    }
    if ($maxViewers !== '') {
        $whereParts[] = "viewcount <= ?";
        $params[] = $maxViewers;
    }
    if (!empty($broadcasterType)) {
        $whereParts[] = "broadcaster_type = ?";
        $params[] = $broadcasterType;
    }

    // Apply sorting based on sortOrder
    $orderBy = " ORDER BY viewcount DESC"; // Default sorting
    switch ($sortOrder) {
        case 'viewCountAsc':
            $orderBy = " ORDER BY viewcount ASC";
            break;
        case 'recentlyStarted':
            $orderBy = " ORDER BY started_at DESC";
            break;
        case 'longestOnline':
            $orderBy = " ORDER BY started_at ASC";
            break;
    }

    $whereClause = !empty($whereParts) ? " WHERE " . implode(' AND ', $whereParts) : "";
    $totalItemsQuery = "SELECT COUNT(*) FROM twitch_filter" . $whereClause;
    $stmt = $pdo->prepare($totalItemsQuery);
    $stmt->execute($params);
    $totalItems = $stmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);

    $fetchQuery = "SELECT * FROM twitch_filter" . $whereClause . $orderBy . " LIMIT $itemsPerPage OFFSET $offset";
    $stmt = $pdo->prepare($fetchQuery);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://kit.fontawesome.com/d894a01a33.js" crossorigin="anonymous"></script>
    <link href="dark-mode.css" rel="stylesheet">
    <link rel="shortcut icon" href="https://wiki.crunchprank.net/_media/favicon.ico" />
    <title>Twitch Gearbox | Stream Filter</title>
    <style>a,a:visited{color:#d63384;}@media(max-width:768px){#socialNav .nav-item{padding:0 5px;}#socialNav{flex-wrap:nowrap;overflow-x:auto;}}.chart-container{position:relative;height:40vh;width:80vw;max-width:100%;}.chart-container canvas{width:100% !important;height:100% !important;}</style>
    <style>
.ui-autocomplete {
    max-height: 200px;
    overflow-y: auto;
    overflow-x: hidden;
    width: 300px;
}
</style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>

</head>
<body>
    <div class="container">
<nav class="navbar navbar-expand-lg navbar-light bg-light rounded-bottom">
    <a class="navbar-brand py-2 px-3" href="index.html">Twitch Gearbox</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item">
                <a class="nav-link" href="tags.php">Popular Tags</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="filter.php">Stream Filter</a>
            </li>
        </ul>
        <ul class="navbar-nav d-flex flex-row flex-nowrap" id="socialNav">
            <li class="nav-item">
                <a class="nav-link" href="https://twitch.tv/crunchprank"><i class="fa-brands fa-twitch"></i></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="https://mastodon.social/@crunchprank"><i class="fa-brands fa-mastodon"></i></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="https://instagram.com/crunchprank"><i class="fa-brands fa-instagram"></i></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="https://crunchprank.net/discord"><i class="fa-brands fa-discord"></i></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="https://patreon.com/crunchprank"><i class="fa-brands fa-patreon"></i></a>
            </li>
        </ul>
    </div>
    <div class="dark-mode-toggle py-2 px-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="darkModeSwitch">
            <label class="form-check-label" for="darkModeSwitch"><i class="fa-solid fa-lightbulb"></i></label>
        </div>
    </div>
</nav>
        <h4 class="mt-3">Search Twitch Streams</h4>
<form action="filter.php" method="GET">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="search">Search Title</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Enter search term" value="<?php echo htmlspecialchars($searchTerm); ?>">
            </div>

            <div class="form-group mb-3">
                <label for="games">Game Names</label>
                <div class="row align-items-center"> 
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="games" name="games" placeholder="e.g., Resident Evil, Resident Evil 2" value="<?php echo htmlspecialchars($gamesInput); ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="useRegex" name="useRegex" <?php echo $useRegex ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="useRegex">Use regex <a href="regex.html">[?]</a></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="tags">Tags</label>
                <div class="row align-items-center"> 
                    <div class="col-md-9">
                        <input type="text" class="form-control" id="tags" name="tags" placeholder="e.g., chill, adhd, cozy" value="<?php echo htmlspecialchars($tagsInput); ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeAllTags" name="includeAllTags" value="1" <?php echo $includeAllTags ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="includeAllTags">Include all tags</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group mb-3">
                <label for="excludeTags">Exclude Tags</label>
                <input type="text" class="form-control" id="excludeTags" name="excludeTags" placeholder="e.g., loud, competitive, rage" value="<?php echo htmlspecialchars($excludeTagsInput ?? ''); ?>">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="broadcasterType">Broadcaster Type</label>
                <select class="form-control" id="broadcasterType" name="broadcasterType">
                    <option value="">Any</option>
                    <option value="normal" <?php echo $broadcasterType === 'normal' ? 'selected' : ''; ?>>Normal</option>
                    <option value="affiliate" <?php echo $broadcasterType === 'affiliate' ? 'selected' : ''; ?>>Affiliate</option>
                    <option value="partner" <?php echo $broadcasterType === 'partner' ? 'selected' : ''; ?>>Partner</option>
                </select>
            </div>

            <div class="form-group mb-3">
                <label for="language">Language</label>
                <select class="form-control" id="language" name="language">
                    <option value="">Any</option>
                    <?php
                    $languages = [
                        "ar" => "Arabic", "asl" => "American Sign Language", "bg" => "Bulgarian", "ca" => "Catalan",
                        "cs" => "Czech", "da" => "Danish", "de" => "German", "el" => "Greek", "en" => "English",
                        "es" => "Spanish", "fi" => "Finnish", "fr" => "French", "hi" => "Hindi", "hu" => "Hungarian",
                        "id" => "Indonesian", "it" => "Italian", "ja" => "Japanese", "ko" => "Korean", "nl" => "Dutch",
                        "no" => "Norwegian", "other" => "Other", "pl" => "Polish", "pt" => "Portuguese", "ro" => "Romanian",
                        "ru" => "Russian"
                    ];

                    foreach ($languages as $code => $name) {
                        echo '<option value="' . $code . '"' . ($languageCode === $code ? ' selected' : '') . '>' . $name . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group mb-3">
                <label for="sortOrder">Sort By</label>
                <select class="form-control" id="sortOrder" name="sortOrder">
                    <option value="viewCountDesc" <?php echo $sortOrder === 'viewCountDesc' ? 'selected' : ''; ?>>Most Viewers</option>
                    <option value="viewCountAsc" <?php echo $sortOrder === 'viewCountAsc' ? 'selected' : ''; ?>>Least Viewers</option>
                    <option value="recentlyStarted" <?php echo $sortOrder === 'recentlyStarted' ? 'selected' : ''; ?>>Recently Started</option>
                    <option value="longestOnline" <?php echo $sortOrder === 'longestOnline' ? 'selected' : ''; ?>>Longest Online</option>
                </select>
            </div>

            <div class="row">
                <div class="col-6 pe-2">
                    <div class="form-group mb-3">
                        <label for="minViewers">Minimum Viewers</label>
                        <input type="number" class="form-control" id="minViewers" name="minViewers" placeholder="Enter minimum viewers" value="<?php echo htmlspecialchars($minViewers); ?>">
                    </div>
                </div>
                <div class="col-6 ps-2">
                    <div class="form-group mb-3">
                        <label for="maxViewers">Maximum Viewers</label>
                        <input type="number" class="form-control" id="maxViewers" name="maxViewers" placeholder="Enter maximum viewers" value="<?php echo htmlspecialchars($maxViewers); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
</form>


        <?php if ($results): ?>
<h4 class="pt-3">Search Results</h4>
<p>Total Streams Found: <?= htmlspecialchars($totalItems) ?></p>
<div class="row">
    <?php foreach ($results as $row): ?>
        <div class="col-md-4 mt-3">
            <div class="card h-100">
                <a href="https://www.twitch.tv/<?= htmlspecialchars($row['username']) ?>" target="_blank">
                    <img src="<?= htmlspecialchars($row['thumbnail_url']) ?: 'placeholder-image-url.jpg' ?>" class="card-img-top" style="object-fit: cover; width: 100%; height: 100%;" alt="Stream Thumbnail">
                </a>
                <div class="text-center" style="margin-bottom: -80px;">
                    <img src="<?= htmlspecialchars($row['profile_image_url']) ?: 'placeholder-profile-image-url.jpg' ?>" class="rounded-circle" style="object-fit: cover; width: 100px; height: 100px; border: 1px #343a40 solid;border-radius: .25rem; position: relative; top: -50px; background-color: white;" alt="Profile Image">
                </div>
                <div class="card-body d-flex flex-column pt-5">
                    <div class="card-content">
                        <h5 class="card-title"><a href="https://www.twitch.tv/<?= htmlspecialchars($row['username']) ?>" target="_blank"><?= htmlspecialchars($row['title']) ?></a></h5>
                        <strong>Game</strong>: <?= htmlspecialchars($row['game_name']) ?><br>
                        <strong>Username</strong>: <?= htmlspecialchars($row['username']) ?><br>
                        <strong>Language</strong>: <?= htmlspecialchars($row['language']) ?><br>
                        <strong>Broadcaster Type</strong>: <?= htmlspecialchars($row['broadcaster_type']) ?><br>
                        <strong>Viewers</strong>: <?= htmlspecialchars($row['viewcount']) ?><br>
                        <strong>Tags</strong>: <?= str_replace(',', ', ', htmlspecialchars($row['tags'])) ?>
                    </div>
                    <div class="card-footer mt-auto">
                        <?php
                        // Date conversion ('started_at' is in UTC)
                        $startTime = new DateTime($row['started_at'], new DateTimeZone('UTC'));
                        $currentTime = new DateTime("now", new DateTimeZone('UTC'));
                        $interval = $startTime->diff($currentTime);
                        $timeString = $interval->d > 0 ? $interval->format("%d day(s) ") : "";
                        $timeString .= $interval->h > 0 ? $interval->format("%h hour(s) ") : "";
                        $timeString .= $interval->format("%i minute(s) ago");
                        ?>
                        <strong>Started</strong>: <?= $timeString ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

            <nav aria-label="Page navigation">
                <ul class="pagination pt-3">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET'): ?>
            <p>No results found.</p>
        <?php endif; ?>
        <footer class="bg-light text-center text-lg-start mt-4 rounded-top">
            <div class="text-center p-3 small">
                Made by <a href="https://crunchprank.net">crunchprank</a> | Not affiliated with Twitch | <a href="privacy.html">Privacy Policy</a> | Find me at
                <a href="https://twitch.tv/crunchprank"><i class="px-1 fa-brands fa-twitch"></i></a>
                <a href="https://mastodon.social/@crunchprank"><i class="pe-1 fa-brands fa-mastodon"></i></a>
                <a href="https://instagram.com/crunchprank"><i class="pe-1 fa-brands fa-instagram"></i></a>
                <a href="https://crunchprank.net/discord"><i class="pe-1 fa-brands fa-discord"></i></a>
                <a href="https://patreon.com/crunchprank"><i class="fa-brands fa-patreon"></i></a>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script>
        function toggleDarkMode() {
            const isDarkMode = document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', isDarkMode);
        }
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
            document.getElementById('darkModeSwitch').checked = true;
        }
        document.getElementById('darkModeSwitch').addEventListener('change', toggleDarkMode);
    </script>
<script>
$(document).ready(function() {
    $("#games").autocomplete({
        source: function(request, response) {
            var term = request.term.split(/,\s*/).pop();
            $.getJSON('autocomplete.php', { term: term }, function(data) {
                response(data);
            });
        },
        search: function() {

            var term = this.value.split(/,\s*/).pop();
            if (term.length < 2) {
                return false;
            }
        },
        focus: function() {

            return false;
        },
        select: function(event, ui) {
            var terms = this.value.split(/,\s*/);
            terms.pop();
            terms.push(ui.item.value);
            terms.push("");
            this.value = terms.join(", ");
            return false;
        },
        minLength: 2
    });

    // Disable or enable autocomplete. Currently broken. Will look into it later.
    $('#useRegex').change(function() {
        if (this.checked) {
            // Disable autocomplete
            $("#games").autocomplete("disable");
        } else {
            // Enable autocomplete
            $("#games").autocomplete("enable");
        }
    });
});
</script>


</body>
</html>
