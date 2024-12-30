<?php
/**
 * Plugin Name: Best Enhanced YouTube Feed
 * Description: Manage YouTube feeds, shuffle videos, and archive functionality.
 * Version: 3.2-1223
 * Author: USTemplate
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Enqueue Admin and Frontend Assets
function enhanced_youtube_feed_assets() {
    if (is_admin()) {
        wp_enqueue_style('enhanced-youtube-feed-admin-style', plugin_dir_url(__FILE__) . 'css/main.css');
    } else {
        wp_enqueue_style('enhanced-youtube-feed-frontend-style', plugin_dir_url(__FILE__) . 'css/frontend.css');
        wp_enqueue_script('enhanced-youtube-feed-frontend-script', plugins_url('js/script.js', __FILE__), ['jquery'], null, true);
    }
}
add_action('admin_enqueue_scripts', 'enhanced_youtube_feed_assets');
add_action('wp_enqueue_scripts', 'enhanced_youtube_feed_assets');

// Create and Update Database Tables on Activation
function create_or_update_youtube_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Feeds Table
    $feeds_table = $wpdb->prefix . 'youtube_feeds';
    $sql_feeds = "CREATE TABLE IF NOT EXISTS $feeds_table (
        id INT NOT NULL AUTO_INCREMENT,
        feed_name VARCHAR(255) NOT NULL,
        channel_ids TEXT DEFAULT NULL,
        group_id INT DEFAULT NULL,
        header TEXT DEFAULT NULL,
        topic TEXT DEFAULT NULL,
        rows INT DEFAULT 2,
        columns INT DEFAULT 3,
        shuffle BOOLEAN DEFAULT 0,
        archive BOOLEAN DEFAULT 0,
        active BOOLEAN DEFAULT 1,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Channels Table
    $channels_table = $wpdb->prefix . 'youtube_channels';
    $sql_channels = "CREATE TABLE IF NOT EXISTS $channels_table (
        id INT NOT NULL AUTO_INCREMENT,
        channel_id VARCHAR(255) NOT NULL,
        channel_name VARCHAR(255) NOT NULL,
        group_id INT DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // YouTube Videos Table
    $videos_table = $wpdb->prefix . 'youtube_videos';
    $sql_videos = "CREATE TABLE IF NOT EXISTS $videos_table (
        id INT NOT NULL AUTO_INCREMENT,
        video_id VARCHAR(255) NOT NULL,
        channel_id VARCHAR(255) NOT NULL,
        published_date DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Archive Table
    $archive_table = $wpdb->prefix . 'youtube_archive';
    $sql_archive = "CREATE TABLE IF NOT EXISTS $archive_table (
        id INT NOT NULL AUTO_INCREMENT,
        video_id VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        channel_name VARCHAR(255) NOT NULL,
        date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
        archived BOOLEAN DEFAULT 1,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Playlists Table
    $playlists_table = $wpdb->prefix . 'youtube_playlists';
    $sql_playlists = "CREATE TABLE IF NOT EXISTS $playlists_table (
        id INT NOT NULL AUTO_INCREMENT,
        playlist_id VARCHAR(255) NOT NULL,
        playlist_name VARCHAR(255) NOT NULL,
        video_ids TEXT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_feeds);
    dbDelta($sql_channels);
    dbDelta($sql_videos);
    dbDelta($sql_archive);
    dbDelta($sql_playlists);
}
register_activation_hook(__FILE__, 'create_or_update_youtube_tables');

// Admin Menu
// Add youtube_feed_menu
function youtube_feed_menu() {
    add_menu_page(
        'YouTube Feeds',
		'YouTube Feeds',
		'manage_options',
		'youtube-feeds',
		'youtube_feed_admin_page',
		'dashicons-video-alt3',
		20
    );
    // YouTube Videos Menu
    add_submenu_page(
        'youtube-feeds',
		'YouTube Videos',
		'Videos',
		'manage_options',
		'youtube-videos',
		'youtube_videos_admin_page'
    );

    add_submenu_page(
        'youtube-feeds',
        'Manage Channels',
        'Channels',
        'manage_options',
        'youtube-channels',
        'youtube_channels_admin_page'
    );

    add_submenu_page(
        'youtube-feeds',
        'Archive',
        'Archive',
        'manage_options',
        'youtube-archive',
        'youtube_archive_admin_page'
    );

    add_submenu_page(
        'youtube-feeds',
        'Playlists',
        'Playlists',
        'manage_options',
        'youtube-playlists',
        'youtube_playlists_admin_page'
    );
}
add_action('admin_menu', 'youtube_feed_menu');
// Admin Page for Feeds
function youtube_feed_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_feeds';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $feed_name = isset($_POST['feed_name']) ? sanitize_text_field($_POST['feed_name']) : '';
        $channel_ids = isset($_POST['channel_ids']) ? sanitize_textarea_field($_POST['channel_ids']) : null;
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
        $header = isset($_POST['header']) ? sanitize_text_field($_POST['header']) : '';
        $topic = isset($_POST['topic']) ? sanitize_textarea_field($_POST['topic']) : '';
        $rows = isset($_POST['rows']) ? intval($_POST['rows']) : 2;
        $columns = isset($_POST['columns']) ? intval($_POST['columns']) : 3;
        $shuffle = isset($_POST['shuffle']) ? 1 : 0;
        $archive = isset($_POST['archive']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;

        if ($_POST['action'] === 'add') {
            $next_id = (int)$wpdb->get_var("SELECT MAX(id) FROM $table_name") + 1;
            $wpdb->insert($table_name, [
                'id' => $next_id,
                'feed_name' => $feed_name,
                'channel_ids' => $channel_ids,
                'group_id' => $group_id,
                'header' => $header,
                'topic' => $topic,
                'rows' => $rows,
                'columns' => $columns,
                'shuffle' => $shuffle,
                'archive' => $archive,
                'active' => $active
            ]);
	   }elseif ($_POST['action'] === 'edit') {
           $id = intval($_POST['id']);
           $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
           if ($row) {
               ?>
               <form method="post">
                <input type="hidden" name="action" value="edit">
                <!-- Make ID editable -->
                <label for="feed_id">Feed ID:</label>
                <input type="text" name="id" value="<?php echo esc_attr($row['id']); ?>" required>
                <label for="feed_name">Feed Name:</label>
                <input type="text" name="feed_name" value="<?php echo esc_attr($row['feed_name']); ?>" required>
                <label for="channel_ids">Channel IDs:</label>
                <textarea name="channel_ids"><?php echo esc_textarea($row['channel_ids']); ?></textarea>
                <label for="group_id">Group ID:</label>
                <input type="number" name="group_id" value="<?php echo esc_attr($row['group_id']); ?>" required>
                <label for="header">Header:</label>
                <textarea name="header"><?php echo esc_textarea($row['header']); ?></textarea>
                <label for="topic">Topic:</label>
                <textarea name="topic"><?php echo esc_textarea($row['topic']); ?></textarea>
                <label for="rows">Rows:</label>
                <input type="number" name="rows" value="<?php echo esc_attr($row['rows']); ?>" required>
                <label for="columns">Columns:</label>
                <input type="number" name="columns" value="<?php echo esc_attr($row['columns']); ?>" required>
                <label for="shuffle">Shuffle:</label>
                <input type="checkbox" name="shuffle" <?php checked($row['shuffle'], 1); ?>>
                <label for="archive">Archive:</label>
                <input type="checkbox" name="archive" <?php checked($row['archive'], 1); ?>>
                <label for="active">Active:</label>
                <input type="checkbox" name="active" <?php checked($row['active'], 1); ?>>
                <button type="submit" class="button button-primary">Update Feed</button>
            </form>
            <?php
        }
     
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $wpdb->delete($table_name, ['id' => $id]);
        }
    }

    $feeds = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>YouTube Feed Management</h1>
        <h2>Add/Edit Feed</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <table class="form-table">
                <tr>
                    <th>ID</th>
                    <td><input type="text" name="id" readonly></td>
                </tr>
                <tr>
                    <th>Feed Name</th>
                    <td><input type="text" name="feed_name" required></td>
                </tr>
                <tr>
                    <th>Channel IDs</th>
                    <td><textarea name="channel_ids"></textarea></td>
                </tr>
                <tr>
                    <th>Group ID</th>
                    <td><input type="number" name="group_id"></td>
                </tr>
                <tr>
                    <th>Header</th>
                    <td><textarea name="header" required></textarea></td>
                </tr>
                <tr>
                    <th>Topic</th>
                    <td><textarea name="topic"></textarea></td>
                </tr>
                <tr>
                    <th>Rows</th>
                    <td><input type="number" name="rows" value="2"></td>
                </tr>
                <tr>
                    <th>Columns</th>
                    <td><input type="number" name="columns" value="3"></td>
                </tr>
                <tr>
                    <th>Shuffle</th>
                    <td><input type="checkbox" name="shuffle"></td>
                </tr>
                <tr>
                    <th>Archive</th>
                    <td><input type="checkbox" name="archive"></td>
                </tr>
                <tr>
                    <th>Active</th>
                    <td><input type="checkbox" name="active" checked></td>
                </tr>
            </table>
            <button type="submit" class="button button-primary">Add/Update Feed</button>
        </form>

        <h2>Existing Feeds</h2>
        <table class="youtube-admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Feed Name</th>
                    <th>Channels</th>
                    <th>Group ID</th>
                    <th>Header</th>
                    <th>Topic</th>
                    <th>Rows</th>
                    <th>Columns</th>
                    <th>Shuffle</th>
                    <th>Archive</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeds as $feed): ?>
                    <tr>
                        <td><?php echo $feed->id; ?></td>
                        <td><?php echo esc_html($feed->feed_name); ?></td>
                        <td><?php echo esc_html($feed->channel_ids); ?></td>
                        <td><?php echo esc_html($feed->group_id); ?></td>
                        <td><?php echo esc_html($feed->header); ?></td>
                        <td><?php echo esc_html($feed->topic); ?></td>
                        <td><?php echo esc_html($feed->rows); ?></td>
                        <td><?php echo esc_html($feed->columns); ?></td>
                        <td><?php echo $feed->shuffle ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $feed->archive ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $feed->active ? 'Yes' : 'No'; ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?php echo $feed->id; ?>">
                                <button type="submit" class="button">Edit</button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $feed->id; ?>">
                                <button type="submit" class="button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

//Admin Page For YouTube video
function youtube_videos_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_videos';

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add']) && isset($_POST['video_id']) && isset($_POST['channel_id']) && isset($_POST['published_date'])) {
            $video_id = sanitize_text_field($_POST['video_id']);
            $channel_id = sanitize_text_field($_POST['channel_id']);
            $published_date = sanitize_text_field($_POST['published_date']);

            $wpdb->insert($table_name, [
                'video_id' => $video_id,
                'channel_id' => $channel_id,
                'published_date' => $published_date
            ]);
        } elseif (isset($_POST['edit']) && isset($_POST['video_id']) && isset($_POST['channel_id']) && isset($_POST['published_date']) && isset($_POST['id'])) {
            $video_id = sanitize_text_field($_POST['video_id']);
            $channel_id = sanitize_text_field($_POST['channel_id']);
            $published_date = sanitize_text_field($_POST['published_date']);
            $id = intval($_POST['id']);

            $wpdb->update($table_name, [
                'video_id' => $video_id,
                'channel_id' => $channel_id,
                'published_date' => $published_date
            ], ['id' => $id]);
        } elseif (isset($_POST['delete']) && isset($_POST['video_id'])) {
            $wpdb->delete($table_name, ['id' => intval($_POST['video_id'])]);
        }
    }

    // Fetch existing videos
    $videos = $wpdb->get_results("SELECT * FROM $table_name");

    ob_start();
    ?>
    <div class="wrap">
        <h1>YouTube Videos</h1>
        <h2>Add New Video</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Video ID</th>
                    <td><input type="text" name="video_id" required></td>
                </tr>
                <tr>
                    <th>Channel ID</th>
                    <td><input type="text" name="channel_id" required></td>
                </tr>
                <tr>
                    <th>Published Date</th>
                    <td><input type="date" name="published_date" required></td>
                </tr>
            </table>
            <button type="submit" name="add" class="button button-primary">Add Video</button>
        </form>

        <h2>Existing Videos</h2>
        <table class="youtube-admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Video ID</th>
                    <th>Channel ID</th>
                    <th>Published Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($videos as $video): ?>
                    <tr>
                        <td><?php echo esc_html($video->id); ?></td>
                        <td><?php echo esc_html($video->video_id); ?></td>
                        <td><?php echo esc_html($video->channel_id); ?></td>
                        <td><?php echo esc_html($video->published_date); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo esc_attr($video->id); ?>">
                                <input type="text" name="video_id" value="<?php echo esc_attr($video->video_id); ?>" required>
                                <input type="text" name="channel_id" value="<?php echo esc_attr($video->channel_id); ?>" required>
                                <input type="date" name="published_date" value="<?php echo esc_attr($video->published_date); ?>" required>
                                <button type="submit" name="edit" class="button button-primary">Update</button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="video_id" value="<?php echo $video->id; ?>">
                                <button type="submit" name="delete" class="button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
add_action('admin_menu', 'youtube_feed_menu');
}

/* // Ensure Admin Menu Item for YouTube Videos: 
function youtube_feed_menu() {
    add_menu_page(
        'YouTube Feeds', 'YouTube Feeds', 'manage_options', 'youtube-feeds', 'youtube_feed_admin_page', 'dashicons-video-alt3', 20
    );

    // YouTube Videos Menu
    add_submenu_page(
        'youtube-feeds', 'YouTube Videos', 'Videos', 'manage_options', 'youtube-videos', 'youtube_videos_admin_page'
    );

    // Additional Menu Items 
    add_submenu_page(
        'youtube-feeds', 'Manage Channels', 'Channels', 'manage_options', 'youtube-channels', 'youtube_channels_admin_page'
    );
    add_submenu_page(
        'youtube-feeds', 'Archive', 'Archive', 'manage_options', 'youtube-archive', 'youtube_archive_admin_page'
    );
    add_submenu_page(
        'youtube-feeds', 'Playlists', 'Playlists', 'manage_options', 'youtube-playlists', 'youtube_playlists_admin_page'
    );
}
add_action('admin_menu', 'youtube_feed_menu');
*/

// Admin Page for Channels
function youtube_channels_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_channels';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $channel_id = isset($_POST['channel_id']) ? sanitize_text_field($_POST['channel_id']) : '';
        $channel_name = isset($_POST['channel_name']) ? sanitize_text_field($_POST['channel_name']) : '';
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;

        if ($_POST['action'] === 'add') {
            $next_id = (int)$wpdb->get_var("SELECT MAX(id) FROM $table_name") + 1;
            $wpdb->insert($table_name, [
                'id' => $next_id,
                'channel_id' => $channel_id,
                'channel_name' => $channel_name,
                'group_id' => $group_id
            ]);
        } elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                ?>
                <form method="post">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo esc_attr($row['id']); ?>">
                    <input type="text" name="channel_id" value="<?php echo esc_attr($row['channel_id']); ?>" required>
                    <input type="text" name="channel_name" value="<?php echo esc_attr($row['channel_name']); ?>" required>
                    <input type="number" name="group_id" value="<?php echo esc_attr($row['group_id']); ?>" required>
                    <button type="submit" class="button button-primary">Update Channel</button>
                </form>
                <?php
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $wpdb->delete($table_name, ['id' => $id]);
        }
    }

    $channels = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>Manage Channels</h1>
        <h2>Add Channel</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <table class="form-table">
                <tr>
                    <th>Channel ID</th>
                    <td><input type="text" name="channel_id" required></td>
                </tr>
                <tr>
                    <th>Channel Name</th>
                    <td><input type="text" name="channel_name" required></td>
                </tr>
                <tr>
                    <th>Group ID</th>
                    <td><input type="number" name="group_id" required></td>
                </tr>
            </table>
            <button type="submit" class="button button-primary">Add Channel</button>
        </form>

        <h2>Existing Channels</h2>
        <table class="youtube-admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Channel ID</th>
                    <th>Channel Name</th>
                    <th>Group ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $channel): ?>
                    <tr>
                        <td><?php echo $channel->id; ?></td>
                        <td><?php echo esc_html($channel->channel_id); ?></td>
                        <td><?php echo esc_html($channel->channel_name); ?></td>
                        <td><?php echo esc_html($channel->group_id); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?php echo $channel->id; ?>">
                                <button type="submit" class="button">Edit</button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $channel->id; ?>">
                                <button type="submit" class="button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Admin Page for Archives
function youtube_archive_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_archive';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $video_id = isset($_POST['video_id']) ? sanitize_text_field($_POST['video_id']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $channel_name = isset($_POST['channel_name']) ? sanitize_text_field($_POST['channel_name']) : '';
        $date_added = current_time('mysql');

        if ($_POST['action'] === 'add') {
            $next_id = (int)$wpdb->get_var("SELECT MAX(id) FROM $table_name") + 1;
            $wpdb->insert($table_name, [
                'id' => $next_id,
                'video_id' => $video_id,
                'title' => $title,
                'channel_name' => $channel_name,
                'date_added' => $date_added,
                'archived' => 1
            ]);
        } elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                ?>
                <form method="post">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo esc_attr($row['id']); ?>">
                    <input type="text" name="video_id" value="<?php echo esc_attr($row['video_id']); ?>" required>
                    <input type="text" name="title" value="<?php echo esc_attr($row['title']); ?>" required>
                    <input type="text" name="channel_name" value="<?php echo esc_attr($row['channel_name']); ?>" required>
                    <button type="submit" class="button button-primary">Update Archive</button>
                </form>
                <?php
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $wpdb->delete($table_name, ['id' => $id]);
        }
    }

    $archives = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>Manage Archives</h1>
        <h2>Add/Edit Archive</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <table class="form-table[_{{{CITATION{{{_1{](https://github.com/wgminer/playste/tree/d2c8e817851393ddb861d0768fd9e1838c1dba3a/api%2Fapplication%2Fviews%2Fdashboard.php)
        <tr>
            <th>Video ID</th>
            <td><input type="text" name="video_id" required></td>
        </tr>
        <tr>
            <th>Title</th>
            <td><input type="text" name="title" required></td>
        </tr>
        <tr>
            <th>Channel Name</th>
            <td><input type="text" name="channel_name" required></td>
        </tr>
    </table>
    <button type="submit" class="button button-primary">Add/Update Archive</button>
</form>

<h2>Existing Archives</h2>
<table class="youtube-admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Video ID</th>
            <th>Title</th>
            <th>Channel Name</th>
            <th>Date Added</th>
            <th>Archived</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($archives as $archive): ?>
            <tr>
                <td><?php echo $archive->id; ?></td>
                <td><?php echo esc_html($archive->video_id); ?></td>
                <td><?php echo esc_html($archive->title); ?></td>
                <td><?php echo esc_html($archive->channel_name); ?></td>
                <td><?php echo esc_html($archive->date_added); ?></td>
                <td><?php echo $archive->archived ? 'Yes' : 'No'; ?></td>
                <td>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo $archive->id; ?>">
                        <button type="submit" class="button">Edit</button>
                    </form>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $archive->id; ?>">
                        <button type="submit" class="button">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php
}

function youtube_playlists_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'youtube_playlists';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $playlist_id = isset($_POST['playlist_id']) ? sanitize_text_field($_POST['playlist_id']) : '';
        $playlist_name = isset($_POST['playlist_name']) ? sanitize_text_field($_POST['playlist_name']) : '';
        $video_links = isset($_POST['video_links']) ? explode(',', sanitize_textarea_field($_POST['video_links'])) : [];
        $video_ids = array_map('extract_video_id', $video_links);
        $video_ids = implode(',', $video_ids);

        if ($_POST['action'] === 'add') {
            $next_id = (int)$wpdb->get_var("SELECT MAX(id) FROM $table_name") + 1;
            $wpdb->insert($table_name, [
                'id' => $next_id,
                'playlist_id' => $playlist_id,
                'playlist_name' => $playlist_name,
                'video_ids' => $video_ids
            ]);
        } elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                ?>
                <form method="post">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo esc_attr($row['id']); ?>">
                    <input type="text" name="playlist_id" value="<?php echo esc_attr($row['playlist_id']); ?>" required>
                    <input type="text" name="playlist_name" value="<?php echo esc_attr($row['playlist_name']); ?>" required>
                    <textarea name="video_links" required><?php echo esc_textarea($row['video_ids']); ?></textarea>
                    <button type="submit" class="button button-primary">Update Playlist</button>
                </form>
                <?php
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $wpdb->delete($table_name, ['id' => $id]);
        }
    }

    $playlists = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>Manage Playlists</h1>
        <h2>Add/Edit Playlist</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <table class="form-table">
                <tr>
                    <th>Playlist ID</th>
                    <td><input type="text" name="playlist_id" required></td>
                </tr>
                <tr>
                    <th>Playlist Name</th>
                    <td><input type="text" name="playlist_name" required></td>
                </tr>
                <tr>
                    <th>Video Links (comma-separated)</th>
                    <td><textarea name="video_links" required></textarea></td>
                </tr>
            </table>
            <button type="submit" class="button button-primary">Add Playlist</button>
        </form>

        <h2>Existing Playlists</h2>
        <table class="youtube-admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Playlist ID</th>
                    <th>Playlist Name</th>
                    <th>Video IDs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($playlists as $playlist): ?>
                    <tr>
                        <td><?php echo $playlist->id; ?></td>
                        <td><?php echo esc_html($playlist->playlist_id); ?></td>
                        <td><?php echo esc_html($playlist->playlist_name); ?></td>
                        <td><?php echo esc_html($playlist->video_ids); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?php echo $playlist->id; ?>">
                                <button type="submit" class="button">Edit</button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $playlist->id; ?>">
                                <button type="submit" class="button">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
add_action('admin_menu', 'youtube_feed_menu');
/* playlist By Copilot suggestion12:08 AM 12/28/2024 */
// Extract YouTube Video ID from Link
function extract_video_id($link) {
    parse_str(parse_url($link, PHP_URL_QUERY), $query);
    return $query['v'];
}

// Shortcodes
/* //disable Fetching */
// Ensure fetch function exactly tracing correct beyond everything:
function fetch_latest_video($channel_id) {
    global $wpdb;

    // Use the provided API key
    $api_key = 'AIzaSyArYuApJCHoB5mhhred1SFEWWvafTMQyJo'; 

    $response = wp_remote_get("https://www.googleapis.com/youtube/v3/search?key=$api_key&channelId=$channel_id&order=date&part=snippet&type=video&maxResults=1");
    if (is_wp_error($response)) {
        error_log("An error occurred retrieving video info for channel: $channel_id");
        return '';
    } 
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['items'])) {
        error_log("No video found for channel: $channel_id");
        return '';
    }

    $latest_video = $data['items'][0]['id']['videoId'];
    return $latest_video;
}

// Shortcode Execution with Updated API key usage
function youtube_feed_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => '', 
        'rows' => 2, 
        'columns' => 3, 
        'width' => '100%', 
        'height' => 'auto', 
        'autoplay' => '0'
    ], $atts, 'youtube_feed');
    
    global $wpdb;

    $feed_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}youtube_feeds WHERE id = %d", $atts['id']), ARRAY_A);
    if (!empty($feed_data) && isset($feed_data['channel_ids'])) {
        $channel_ids = explode(',', $feed_data['channel_ids']);
    } else {
        $channel_ids = [];
    }

    $videos = [];
    foreach ($channel_ids as $channel_id) {
        $latest_video = fetch_latest_video($channel_id);
        if ($latest_video) {
            $videos[] = $latest_video;
        }
    }

    ob_start();
    if (!empty($videos)) {
        echo '<div class="youtube-feed-grid" style="display: grid; grid-template-columns: repeat(' . intval($atts['columns']) . ', 1fr); gap: 1rem;">';
        for ($i = 0; i < min($atts['rows'] * $atts['columns'], count($videos)); $i++) {
            $video = $videos[$i];
            echo '<div class="youtube-video" style="box-shadow: 10px 30px 30px rgba(0, 0, 0, 0.1);">';
            echo '<iframe width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '" src="https://www.youtube.com/embed/' . esc_attr($video) . '?autoplay=' . esc_attr($atts['autoplay']) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo 'No videos available';
    }
    return ob_get_clean();
}
add_shortcode('youtube_feed', 'youtube_feed_shortcode');

function youtube_group_feed_shortcode($atts) {
    $atts = shortcode_atts([
        'group_id' => '', 
        'rows' => 2, 
        'columns' => 3, 
        'title' => 'Group', 
        'width' => '560', 
        'height' => '315', 
        'autoplay' => '0'
    ], $atts, 'youtube_group_feed');
    
    global $wpdb;

    $channel_data = $wpdb->get_results($wpdb->prepare("SELECT channel_id, channel_name FROM {$wpdb->prefix}youtube_channels WHERE group_id = %d", $atts['group_id']));

    ob_start();
    if ($atts['title']) {
        echo '<h2>' . esc_html($atts['title']) . '</h2>';
    }
    if (!empty($channel_data)) {
        echo '<div class="youtube-feed-grid" style="display: grid; grid-template-columns: repeat(' . intval($atts['columns']) . ', 1fr); gap: 1rem;">';
        foreach ($channel_data as $channel) {
            $video_id = fetch_latest_video($channel->channel_id);
            
            if ($video_id) {
                echo '<div class="youtube-video" style="box-shadow: 10px 30px 30px rgba(0, 0, 0, 0.1);">';
                echo '<iframe width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '?autoplay=' . esc_attr($atts['autoplay']) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                echo '<p style="text-align: center;">' . esc_html($channel->channel_name) . '</p>';
                echo '</div>';
            } else {
                echo '<p>Unable to fetch latest video for channel: ' . esc_html($channel->channel_name) . '</p>';
            }
        }
        echo '</div>';
    } else {
        echo '';
    }
    return ob_get_clean();
}
add_shortcode('youtube_group_feed', 'youtube_group_feed_shortcode');

// YT FEED Shortcodes
function youtube_feed_shortcode($atts) {
    $atts = shortcode_atts(['id' => '', 'rows' => 2, 'columns' => 3, 'width' => '100%', 'height' => 'auto', 'autoplay' => '0'], $atts, 'youtube_feed');
    global $wpdb;

    $feed_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}youtube_feeds WHERE id = %d", $atts['id']), ARRAY_A);
    if (!empty($feed_data) && isset($feed_data['channel_ids'])) {
        $channel_ids = explode(',', $feed_data['channel_ids']);
    } else {
        $channel_ids = [];
    }

    $videos = [];
    foreach ($channel_ids as $channel_id) {
        $latest_video = fetch_latest_video($channel_id);
        if ($latest_video) {
            $videos[] = $latest_video;
        }
    }

    ob_start();
    if (!empty($videos)) {
        echo '<div class="youtube-feed-grid" style="display: grid; grid-template-columns: repeat(' . intval($atts['columns']) . ', 1fr); gap: 1rem;">';
        for ($i = 0; i < min($atts['rows'] * $atts['columns'], count($videos)); $i++) {
            $video = $videos[$i];
            echo '<div class="youtube-video" style="box-shadow: 10px 30px 30px rgba(0, 0, 0, 0.1);">';
            echo '<iframe width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '" src="https://www.youtube.com/embed/' . esc_attr($video) . '?autoplay=' . esc_attr($atts['autoplay']) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo 'No videos available';
    }
    return ob_get_clean();
}
add_shortcode('youtube_feed', 'youtube_feed_shortcode');

// CSS for Mobile Responsiveness
function custom_youtube_feed_styles() {
    echo '
        <style>
            @media only screen and (max-width: 768px) {
                .youtube-feed-grid {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>
    ';
}
add_action('wp_head', 'custom_youtube_feed_styles');

// YT Group Feed Shortcode
function youtube_group_feed_shortcode($atts) {
    $atts = shortcode_atts(['group_id' => '', 'rows' => 2, 'columns' => 3, 'title' => 'Group', 'width' => '560', 'height' => '315', 'autoplay' => '0'], $atts, 'youtube_group_feed');
    global $wpdb;

    $channel_data = $wpdb->get_results($wpdb->prepare("SELECT channel_id, channel_name FROM {$wpdb->prefix}youtube_channels WHERE group_id = %d", $atts['group_id']));

    ob_start();
    if ($atts['title']) {
        echo '<h2>' . esc_html($atts['title']) . '</h2>';
    }
    if (!empty($channel_data)) {
        echo '<div class="youtube-feed-grid" style="display: grid; grid-template-columns: repeat(' . intval($atts['columns']) . ', 1fr); gap: 1rem;">';
        foreach ($channel_data as $channel) {
            $video_id = fetch_latest_video($channel->channel_id);
            
            if ($video_id) {
                echo '<div class="youtube-video" style="box-shadow: 10px 30px 30px rgba(0, 0, 0, 0.1);">';
                echo '<iframe width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '?autoplay=' . esc_attr($atts['autoplay']) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                echo '<p style="text-align: center;">' . esc_html($channel->channel_name) . '</p>';
                echo '</div>';
            } else {
                echo '<p>Unable to fetch latest video for channel: ' . esc_html($channel->channel_name) . '</p>';
            }
        }
        echo '</div>';
    }
    return ob_get_clean();
}
add_shortcode('youtube_group_feed', 'youtube_group_feed_shortcode');

/* Feed channel with sample 9:27 PM 12/27/2024 */
//Archive Shortcode and Pagination
function youtube_archive_shortcode($atts) {
    global $wpdb;
    $page_limit = 20;
    $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $offset = ($page - 1) * $page_limit;

    // Search functionality
    $search_query = '';
    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $search = sanitize_text_field($_GET['s']);
        $search_query = "AND (title LIKE '%$search%' OR channel_name LIKE '%$search%')";
    }

    $total_videos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}youtube_archive WHERE 1=1 $search_query");
    $total_pages = ceil($total_videos / $page_limit);
    $videos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}youtube_archive WHERE 1=1 $search_query ORDER BY date_added DESC LIMIT $offset, $page_limit");

    ob_start();
    ?>
    <div class="youtube-archive-search">
        <form method="get">
            <input type="text" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" placeholder="Search Archives">
            <button type="submit">Search</button>
        </form>
    </div>
    <?php
    if (!empty($videos)) {
        echo '<div class="youtube-archive">';
        foreach ($videos as $video) {
            echo '<div class="youtube-video">';
            echo '<a href="https://www.youtube.com/watch?v=' . esc_attr($video->video_id) . '" target="_blank">';
            echo esc_html($video->title);
            echo '</a> - ' . esc_html($video->channel_name) . ' - ' . esc_html($video->date_added);
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No videos found.</p>';
    }
    
    // Pagination
    echo '<div class="pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($page == $i) ? 'class="active"' : '';
        echo '<a href="' . get_permalink() . '?paged=' . $i . '&s=' . urlencode(isset($_GET['s']) ? $_GET['s'] : '') . '" ' . $active . '>' . $i . '</a> ';
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('youtube_archive', 'youtube_archive_shortcode');

// YT Playlist Feed Shortcode for display
function youtube_playlist_shortcode($atts) {
    $atts = shortcode_atts(['playlist_id' => ''], $atts, 'youtube_playlist');
    ob_start();
    if (!empty($atts['playlist_id'])) {
        echo '<div class="youtube-playlist">';
        echo '<iframe src="https://www.youtube.com/embed/playlist?list=' . esc_attr($atts['playlist_id']) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        echo '</div>';
    }
    return ob_get_clean();
}
add_shortcode('youtube_playlist', 'youtube_playlist_shortcode');
/*12:23 AM 12/28/2024/9:23 PM 12/27/2024 with no sample */
?>
