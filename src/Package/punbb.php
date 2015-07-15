<?php
/**
 * Punbb exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

$Supported['punbb'] = array('name' => 'PunBB 1', 'prefix' => 'punbb_');
$Supported['punbb']['CommandLine'] = array(
    'avatarpath' => array('Full path of forum avatars.', 'Sx' => '::')
);
$Supported['punbb']['features'] = array(
    'Avatars' => 1,
    'Comments' => 1,
    'Discussions' => 1,
    'Users' => 1,
    'Categories' => 1,
    'Roles' => 1,
    'Attachments' => 1,
    'Permissions' => 1,
    'Tags' => 1,
    'Signatures' => 1,
    'Passwords' => 1
);

class Punbb extends ExportController {

    /** @var bool Path to avatar images */
    protected $AvatarPath = false;

    /** @var string CDN path prefix */
    protected $cdn = '';

    /** @var array Required tables => columns */
    public $SourceTables = array();

    /**
     * Forum-specific export format
     *
     * @todo Project file size / export time and possibly break into multiple files
     *
     * @param ExportModel $Ex
     *
     */
    protected function ForumExport($Ex) {

        $Ex->BeginExport('', 'PunBB 1.*', array('HashMethod' => 'punbb'));

        $this->cdn = $this->Param('cdn', '');

        if ($AvatarPath = $this->Param('avatarpath', false)) {
            if (!$AvatarPath = realpath($AvatarPath)) {
                echo "Unable to access path to avatars: $AvatarPath\n";
                exit(1);
            }

            $this->AvatarPath = $AvatarPath;
        }
        unset($AvatarPath);

        // User.
        $User_Map = array(
            'AvatarID' => array('Column' => 'Photo', 'Filter' => array($this, 'GetAvatarByID')),
            'id' => 'UserID',
            'username' => 'Name',
            'email' => 'Email',
            'timezone' => 'HourOffset',
            'registration_ip' => 'InsertIPAddress',
            'PasswordHash' => 'Password'
        );
        $Ex->ExportTable('User', "
         SELECT
             u.*, u.id AS AvatarID,
             concat(u.password, '$', u.salt) AS PasswordHash,
             from_unixtime(registered) AS DateInserted,
             from_unixtime(last_visit) AS DateLastActive
         FROM :_users u
         WHERE group_id <> 2", $User_Map);

        // Role.
        $Role_Map = array(
            'g_id' => 'RoleID',
            'g_title' => 'Name'
        );
        $Ex->ExportTable('Role', "SELECT * FROM :_groups", $Role_Map);

        // Permission.
        $Permission_Map = array(
            'g_id' => 'RoleID',
            'g_modertor' => 'Garden.Moderation.Manage',
            'g_mod_edit_users' => 'Garden.Users.Edit',
            'g_mod_rename_users' => 'Garden.Users.Delete',
            'g_read_board' => 'Vanilla.Discussions.View',
            'g_view_users' => 'Garden.Profiles.View',
            'g_post_topics' => 'Vanilla.Discussions.Add',
            'g_post_replies' => 'Vanilla.Comments.Add',
            'g_pun_attachment_allow_download' => 'Plugins.Attachments.Download.Allow',
            'g_pun_attachment_allow_upload' => 'Plugins.Attachments.Upload.Allow',

        );
        $Permission_Map = $Ex->FixPermissionColumns($Permission_Map);
        $Ex->ExportTable('Permission', "
      SELECT
         g.*,
         g_post_replies AS `Garden.SignIn.Allow`,
         g_mod_edit_users AS `Garden.Users.Add`,
         CASE WHEN g_title = 'Administrators' THEN 'All' ELSE NULL END AS _Permissions
      FROM :_groups g", $Permission_Map);

        // UserRole.
        $UserRole_Map = array(
            'id' => 'UserID',
            'group_id' => 'RoleID'
        );
        $Ex->ExportTable('UserRole',
            "SELECT
            CASE u.group_id WHEN 2 THEN 0 ELSE id END AS id,
            u.group_id
          FROM :_users u", $UserRole_Map);

        // Signatures.
        $Ex->ExportTable('UserMeta', "
         SELECT
         id,
         'Plugin.Signatures.Sig' AS Name,
         signature
      FROM :_users u
      WHERE u.signature IS NOT NULL", array('id ' => 'UserID', 'signature' => 'Value'));


        // Category.
        $Category_Map = array(
            'id' => 'CategoryID',
            'forum_name' => 'Name',
            'forum_desc' => 'Description',
            'disp_position' => 'Sort',
            'parent_id' => 'ParentCategoryID'
        );
        $Ex->ExportTable('Category', "
      SELECT
        id,
        forum_name,
        forum_desc,
        disp_position,
        cat_id * 1000 AS parent_id
      FROM :_forums f
      UNION

      SELECT
        id * 1000,
        cat_name,
        '',
        disp_position,
        NULL
      FROM :_categories", $Category_Map);

        // Discussion.
        $Discussion_Map = array(
            'id' => 'DiscussionID',
            'poster_id' => 'InsertUserID',
            'poster_ip' => 'InsertIPAddress',
            'closed' => 'Closed',
            'sticky' => 'Announce',
            'forum_id' => 'CategoryID',
            'subject' => 'Name',
            'message' => 'Body'

        );
        $Ex->ExportTable('Discussion', "
      SELECT t.*,
        from_unixtime(p.posted) AS DateInserted,
        p.poster_id,
        p.poster_ip,
        p.message,
        from_unixtime(p.edited) AS DateUpdated,
        eu.id AS UpdateUserID,
        'BBCode' AS Format
      FROM :_topics t
      LEFT JOIN :_posts p
        ON t.first_post_id = p.id
      LEFT JOIN :_users eu
        ON eu.username = p.edited_by", $Discussion_Map);

        // Comment.
        $Comment_Map = array(
            'id' => 'CommentID',
            'topic_id' => 'DiscussionID',
            'poster_id' => 'InsertUserID',
            'poster_ip' => 'InsertIPAddress',
            'message' => 'Body'
        );
        $Ex->ExportTable('Comment', "
            SELECT p.*,
        'BBCode' AS Format,
        from_unixtime(p.posted) AS DateInserted,
        from_unixtime(p.edited) AS DateUpdated,
        eu.id AS UpdateUserID
      FROM :_topics t
      JOIN :_posts p
        ON t.id = p.topic_id
      LEFT JOIN :_users eu
        ON eu.username = p.edited_by
      WHERE p.id <> t.first_post_id;", $Comment_Map);

        if ($Ex->Exists('tags')) {
            // Tag.
            $Tag_Map = array(
                'id' => 'TagID',
                'tag' => 'Name'
            );
            $Ex->ExportTable('Tag', "SELECT * FROM :_tags", $Tag_Map);

            // TagDisucssion.
            $TagDiscussionMap = array(
                'topic_id' => 'DiscussionID',
                'tag_id' => 'TagID'
            );
            $Ex->ExportTable('TagDiscussion', "SELECT * FROM :_topic_tags", $TagDiscussionMap);
        }

        if ($Ex->Exists('attach_files')) {
            // Media.
            $Media_Map = array(
                'id' => 'MediaID',
                'filename' => 'Name',
                'file_mime_type' => 'Type',
                'size' => 'Size',
                'owner_id' => 'InsertUserID'
            );
            $Ex->ExportTable('Media', "
          SELECT f.*,
             concat({$this->cdn}, 'FileUpload/', f.file_path) AS Path,
             from_unixtime(f.uploaded_at) AS DateInserted,
             CASE WHEN post_id IS NULL THEN 'Discussion' ELSE 'Comment' END AS ForeignTable,
             coalesce(post_id, topic_id) AS ForieignID
          FROM :_attach_files f", $Media_Map);
        }


        // End
        $Ex->EndExport();
    }

    function StripMediaPath($AbsPath) {
        if (($Pos = strpos($AbsPath, '/uploads/')) !== false) {
            return substr($AbsPath, $Pos + 9);
        }

        return $AbsPath;
    }

    function FilterPermissions($Permissions, $ColumnName, &$Row) {
        $Permissions2 = unserialize($Permissions);

        foreach ($Permissions2 as $Name => $Value) {
            if (is_null($Value)) {
                $Permissions2[$Name] = false;
            }
        }

        if (is_array($Permissions2)) {
            $Row = array_merge($Row, $Permissions2);
            $this->Ex->CurrentRow = $Row;

            return isset($Permissions2['PERMISSION_ADD_COMMENTS']) ? $Permissions2['PERMISSION_ADD_COMMENTS'] : false;
        }

        return false;
    }

    function ForceBool($Value) {
        if ($Value) {
            return true;
        }

        return false;
    }

    /**
     * Take the user ID, avatar type value and generate a path to the avatar file.
     *
     * @param $Value Row field value.
     * @param $Field Name of the current field.
     * @param $Row All of the current row values.
     *
     * @return null|string
     */
    function GetAvatarByID($Value, $Field, $Row) {
        if (!$this->AvatarPath) {
            return null;
        }

        switch ($Row['avatar']) {
            case 1:
                $Extension = 'gif';
                break;
            case 2:
                $Extension = 'jpg';
                break;
            case 3:
                $Extension = 'png';
                break;
            default:
                return null;
        }

        $AvatarFilename = "{$this->AvatarPath}/{$Value}.$Extension";

        if (file_exists($AvatarFilename)) {
            $AvatarBasename = basename($AvatarFilename);

            return "{$this->cdn}punbb/avatars/$AvatarBasename";
        } else {
            return null;
        }
    }
}

?>
