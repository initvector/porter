<?php
namespace Garden\Porter\Package;

/**
 * SampleName exporter tool.
 *
 * @copyright Vanilla Forums Inc. 2010-2014
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

// Add to the $Supported array so it appears in the dropdown menu. Uncomment next line.
//$Supported['samplename'] = array('name'=> 'Proper Platform Name', 'prefix'=>'x_');

// Optionally, add the features you are supporting. Set all values to 1 or a string for support notes.
// See functions/feature-functions.php VanillaFeatureSet() for array keys.
//$Supported['samplename']['features'] = array('Users' => 1);

class SampleName extends ExportController {
    /**
     * You can use this to require certain tables and columns be present.
     *
     * This can be useful for verifying data integrity. Don't specify more columns
     * than your porter actually requires to avoid forwards-compatibility issues.
     *
     * @var array Required tables => columns
     */
    protected $SourceTables = array(
        'forums' => array(), // This just requires the 'forum' table without caring about columns.
        'posts' => array(),
        'topics' => array(),
        'users' => array('ID', 'user_login', 'user_pass', 'user_email'), // Require specific cols on 'users'
    );

    /**
     * Main export process.
     *
     * @param ExportModel $Ex
     * @see $_Structures in ExportModel for allowed destination tables & columns.
     */
    public function ForumExport($Ex) {
        // Get the characterset for the comments.
        // Usually the comments table is the best target for this.
        $CharacterSet = $Ex->GetCharacterSet('CommentsTableNameGoesHere');
        if ($CharacterSet) {
            $Ex->CharacterSet = $CharacterSet;
        }

        // Reiterate the platform name here to be included in the porter file header.
        $Ex->BeginExport('', 'Proper Platform Name Goes Here');

        // It's usually a good idea to do the porting in the approximate order laid out here.

        // User.
        // Map as much as possible using the $x_Map array for clarity.
        // Key is always the source column name.
        // Value is either the destination column or an array of meta data, usually Column & Filter.
        // If it's a meta array, 'Column' is the destination column name and 'Filter' is a method name to run it thru.
        // Here, 'HTMLDecoder' is a method in ExportModel. Check there for available filters.
        // Assume no filter is needed and only use one if you encounter issues.
        $User_Map = array(
            'Author_ID' => 'UserID',
            'Username' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        // This is the query that the x_Map array above will be mapped against.
        // Therefore, our select statement must cover all the "source" columns.
        // It's frequently necessary to add joins, where clauses, and more to get the data we want.
        // The :_ before the table name is the placeholder for the prefix designated. It gets swapped on the fly.
        $Ex->ExportTable('User', "
         select u.*
         from :_User u
         ", $User_Map);

        // Role.
        // The Vanilla roles table will be wiped by any import. If your current platform doesn't have roles,
        // you can hard code new ones into the select statement. See Vanilla's defaults for a good example.
        $Role_Map = array(
            'Group_ID' => 'RoleID',
            'Name' => 'Name', // We let these arrays end with a comma to prevent typos later as we add.
        );
        $Ex->ExportTable('Role', "
         select *
         from :_tblGroup", $Role_Map);

        // User Role.
        // Really simple matchup.
        // Note that setting Admin=1 on the User table trumps all roles & permissions with "owner" privileges.
        // Whatever account you select during the import will get the Admin=1 flag to prevent permissions issues.
        $UserRole_Map = array(
            'Author_ID' => 'UserID',
            'Group_ID' => 'RoleID',
        );
        $Ex->ExportTable('UserRole', "
         select u.*
         from :_tblAuthor u", $UserRole_Map);

        // Permission.
        // Feel free to add a permission export if this is a major platform or it will see reuse.
        // For small or custom jobs, it's usually not worth it. Just fix them afterward.

        // UserMeta.
        // This is an example of pulling Signatures into Vanilla's UserMeta table.
        // This is often a good place for any extraneous data on the User table too.
        // The Profile Extender addon uses the namespace "Profile.[FieldName]"
        // You can add the appropriately-named fields after the migration and profiles will auto-populate with the migrated data.
        $Ex->ExportTable('UserMeta', "
         select
            Author_ID as UserID,
            'Plugin.Signatures.Sig' as `Name`,
            Signature as `Value`
         from :_tblAuthor
         where Signature <> ''");

        // Category.
        // Be careful to not import hundreds of categories. Try translating huge schemas to Tags instead.
        // Numeric category slugs aren't allowed in Vanilla, so be careful to sidestep those.
        // Don't worry about rebuilding the TreeLeft & TreeRight properties. Vanilla can fix this afterward
        // if you just get the Sort and ParentIDs correct.
        $Category_Map = array(
            'Forum_ID' => 'CategoryID',
            'Forum_name' => 'Name',
        );
        $Ex->ExportTable('Category', "
         select *
         from :_tblCategory c
         ", $Category_Map);

        // Discussion.
        // A frequent issue is for the OPs content to be on the comment/post table, so you may need to join it.
        $Discussion_Map = array(
            'Topic_ID' => 'DiscussionID',
            'Forum_ID' => 'CategoryID',
            'Author_ID' => 'InsertUserID',
            'Subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
        );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $Ex->ExportTable('Discussion', "
         select *,
            FROM_UNIXTIME(Message_date) as Message_date
         from :_tblTopic t
         join :_tblThread th
            on t.Start_Thread_ID = th.Thread_ID", $Discussion_Map);

        // Comment.
        // This is where big migrations are going to get bogged down.
        // Be sure you have indexes created for any columns you are joining on.
        $Comment_Map = array(
            'Thread_ID' => 'CommentID',
            'Topic_ID' => 'DiscussionID',
            'Author_ID' => 'InsertUserID',
            'IP_addr' => 'InsertIPAddress',
            'Message' => array('Column' => 'Body'),
            'Format' => 'Format',
            'Message_date' => array('Column' => 'DateInserted')
        );
        $Ex->ExportTable('Comment', "
         select th.*
         from :_tblThread th", $Comment_Map);

        // UserDiscussion.
        // This is the table for assigning bookmarks/subscribed threads.

        // Media.
        // Attachment data goes here. Vanilla attachments are files under the /uploads folder.
        // This is usually the trickiest step because you need to translate file paths.
        // If you need to export blobs from the database, see the vBulletin porter.

        // Conversations.
        // Private messages often involve the most data manipulation.
        // If you need a large number of complex SQL statements, consider making it a separate method
        // to keep the main process easy to understand. Pass $Ex as a parameter if you do.

        $Ex->EndExport();
    }

}

// Closing PHP tag required.
?>
