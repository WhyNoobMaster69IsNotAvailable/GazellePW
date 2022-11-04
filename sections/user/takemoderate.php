<?

/*************************************************************************\
//--------------Take moderation -----------------------------------------//



\*************************************************************************/

use Gazelle\Manager\Donation;
use Gazelle\Manager\DonationSource;

// Are they being tricky blighters?
if (!$_POST['userid'] || !is_number($_POST['userid'])) {
    error(404);
} elseif (!check_perms('users_mod')) {
    error(403);
}
authorize();
// End checking for moronity

$UserID = $_POST['userid'];
$DeleteKeys = false;

// Variables for database input
$Class = (int)$_POST['Class'];
$Username = db_string($_POST['Username']);
$Title = db_string(Text::full_format($_POST['Title']));
$Donor = isset($_POST['Donor']) ? 1 : 0;
$Artist = isset($_POST['Artist']) ? 1 : 0;
$SecondaryClasses = isset($_POST['secondary_classes']) ? $_POST['secondary_classes'] : array();
foreach ($SecondaryClasses as $i => $Val) {
    if (!is_number($Val)) {
        unset($SecondaryClasses[$i]);
    }
}
$Visible = isset($_POST['Visible']) ? 1 : 0;
$Invites = (int)$_POST['Invites'];
$SupportFor = db_string($_POST['SupportFor']);
$Pass = $_POST['ChangePassword'];
$Warned = isset($_POST['Warned']) ? 1 : 0;
$Uploaded = $Downloaded = $BonusPoints = null;
if (isset($_POST['Uploaded']) && isset($_POST['Downloaded'])) {
    $Uploaded = ($_POST['Uploaded'] === '' ? 0 : $_POST['Uploaded']);
    if ($Arithmetic = strpbrk($Uploaded, '+-')) {
        $Uploaded += max(-$Uploaded, Format::get_bytes($Arithmetic));
    }
    $Downloaded = ($_POST['Downloaded'] === '' ? 0 : $_POST['Downloaded']);
    if ($Arithmetic = strpbrk($Downloaded, '+-')) {
        $Downloaded += max(-$Downloaded, Format::get_bytes($Arithmetic));
    }
    if (!is_number($Uploaded) || !is_number($Downloaded)) {
        error(0);
    }
}
if (isset($_POST['BonusPoints'])) {
    if (empty($_POST['BonusPoints'])) {
        $BonusPoints = 0;
    } elseif ($_POST['BonusPoints'] != strval(floatval($_POST['BonusPoints']))) {
        error(0);
    } else {
        $BonusPoints = round(floatval($_POST['BonusPoints']), 5);
    }
}
$FLTokens = isset($_POST['FLTokens']) ? $_POST['FLTokens'] : 0;
if (!is_number($FLTokens)) {
    error(0);
}

$WarnLength = (int)$_POST['WarnLength'];
$ExtendWarning = (int)$_POST['ExtendWarning'];
$ReduceWarning = (int)$_POST['ReduceWarning'];
$WarnReason = $_POST['WarnReason'];
$UserReason = $_POST['UserReason'];
$DisableAvatar = isset($_POST['DisableAvatar']) ? 1 : 0;
$DisableInvites = isset($_POST['DisableInvites']) ? 1 : 0;
$DisablePosting = isset($_POST['DisablePosting']) ? 1 : 0;
$DisablePoints = isset($_POST['DisablePoints']) ? 1 : 0;
$DisableForums = isset($_POST['DisableForums']) ? 1 : 0;
$DisableTagging = isset($_POST['DisableTagging']) ? 1 : 0;
$DisableUpload = isset($_POST['DisableUpload']) ? 1 : 0;
$DisableWiki = isset($_POST['DisableWiki']) ? 1 : 0;
$DisablePM = isset($_POST['DisablePM']) ? 1 : 0;
$DisableIRC = isset($_POST['DisableIRC']) ? 1 : 0;
$DisableRequests = isset($_POST['DisableRequests']) ? 1 : 0;
$DisableLeech = isset($_POST['DisableLeech']) ? 0 : 1;
$LockedAccount = isset($_POST['LockAccount']) ? 1 : 0;
$LockType = $_POST['LockReason'];
$DisableCheckAll = isset($_POST['DisableCheckAll']) ? 1 : 0;
$DisableCheckSelf = isset($_POST['DisableCheckSelf']) ? 1 : 0;

$RestrictedForums = db_string(trim($_POST['RestrictedForums']));
$PermittedForums = db_string(trim($_POST['PermittedForums']));
$EnableUser = (int)$_POST['UserStatus'];
$ResetRatioWatch = isset($_POST['ResetRatioWatch']) ? 1 : 0;
$ResetPasskey = isset($_POST['ResetPasskey']) ? 1 : 0;
$ResetAuthkey = isset($_POST['ResetAuthkey']) ? 1 : 0;
$SendHackedMail = isset($_POST['SendHackedMail']) ? 1 : 0;
if ($SendHackedMail && !empty($_POST['HackedEmail'])) {
    $HackedEmail = $_POST['HackedEmail'];
} else {
    $SendHackedMail = false;
}
$MergeStatsFrom = db_string($_POST['MergeStatsFrom']);
$Reason = db_string($_POST['Reason']);
$HeavyUpdates = array();
$LightUpdates = array();

// Get user info from the database

$DB->query("
	SELECT
		m.Username,
		m.IP,
		m.Email,
		m.PermissionID,
		p.Level AS Class,
		m.Title,
		m.Enabled,
		m.Uploaded,
		m.Downloaded,
		m.BonusPoints,
		m.Invites,
		m.can_leech,
		m.Visible,
		i.AdminComment,
		m.torrent_pass,
		i.Donor,
		i.Artist,
		i.Warned,
		i.SupportFor,
		i.RestrictedForums,
		i.PermittedForums,
		DisableAvatar,
		DisableInvites,
		DisablePosting,
		DisableForums,
		DisableTagging,
		DisableUpload,
		DisableWiki,
		DisablePM,
		DisableIRC,
		DisableRequests,
		DisableCheckAll,
		DisableCheckSelf,
		DisablePoints,
		m.RequiredRatio,
		m.FLTokens,
		i.RatioWatchEnds,
		la.Type,
		SHA1(i.AdminComment) AS CommentHash,
		GROUP_CONCAT(l.PermissionID SEPARATOR ',') AS SecondaryClasses
	FROM users_main AS m
		JOIN users_info AS i ON i.UserID = m.ID
		LEFT JOIN permissions AS p ON p.ID = m.PermissionID
		LEFT JOIN users_levels AS l ON l.UserID = m.ID
		LEFT JOIN locked_accounts AS la ON la.UserID = m.ID
	WHERE m.ID = $UserID
	GROUP BY m.ID");

if (!$DB->has_results()) { // If user doesn't exist
    header("Location: log.php?search=User+$UserID");
}

$Cur = $DB->next_record(MYSQLI_ASSOC, false);
if ($_POST['comment_hash'] != $Cur['CommentHash']) {
    error(t('server.user.somebody_else_has_moderated'));
}

//NOW that we know the class of the current user, we can see if one staff member is trying to hax0r us.
if (!check_perms('users_mod', $Cur['Class'])) {
    //Son of a fucking bitch
    error(403);
    die();
}
$donation = new Donation();
$OldDonation = $donation->info($LoggedUser['ID']);
$OldRank = $OldDonation['Rank'] >= MAX_RANK ? MAX_RANK : $OldDonation['Rank'];
$OldTotalRank = $OldDonation['TotalRank'];
if (!empty($_POST['donation_value']) && is_numeric($_POST['donation_value'])) {
    $donation->moderatorDonate($UserID, $_POST['donation_value'], $_POST['donation_currency'], $_POST['donation_reason'], DonationSource::AddPoint, $LoggedUser['ID']);
} elseif ($OldRank != $_POST['donor_rank'] || $OldTotalRank != $_POST['total_donor_rank']) {
    $donation->moderatorAdjust($UserID, $_POST['donor_rank'], $_POST['total_donor_rank'], $_POST['reason'], $LoggedUser['ID']);
}


// If we're deleting the user, we can ignore all the other crap

if ($_POST['UserStatus'] === 'delete' && check_perms('users_delete_users')) {
    Misc::write_log("User account $UserID (" . $Cur['Username'] . ") was deleted by " . $LoggedUser['Username']);
    $DB->query("
		DELETE FROM users_main
		WHERE id = $UserID");
    $DB->query("
		DELETE FROM users_info
		WHERE UserID = $UserID");
    $Cache->delete_value("user_info_$UserID");

    Tracker::update_tracker('remove_user', array('passkey' => $Cur['torrent_pass']));

    header("Location: log.php?search=User+$UserID");
    die();
}

// User was not deleted. Perform other stuff.

$UpdateSet = array();
$EditSummary = array();
$TrackerUserUpdates = array('passkey' => $Cur['torrent_pass']);

$QueryID = G::$DB->get_query_id();

if ($LockType == '---' || $LockedAccount == 0) {
    if ($Cur['Type']) {
        $DB->query("DELETE FROM locked_accounts WHERE UserID = '" . $UserID . "'");
        $EditSummary[] = 'Account unlocked';
        $Cache->delete_value('user_' . $Cur['torrent_pass']);
    }
} else if (!$Cur['Type'] || $Cur['Type'] != $LockType) {
    $DB->query("INSERT INTO locked_accounts (UserID, Type)
				VALUES ('" . $UserID . "', '" . $LockType . "')
				ON DUPLICATE KEY UPDATE Type = '" . $LockType . "'");
    $Cache->delete_value('user_' . $Cur['torrent_pass']);

    if ($Cur['Type'] != $LockType) {
        $EditSummary[] = 'Account lock reason changed to ' . $LockType;
    } else {
        $EditSummary[] = 'Account locked (' . $LockType . ')';
    }
}
$Cache->delete_value("user_info_" . $UserID);
$DB->set_query_id($QueryID);

if ($_POST['ResetRatioWatch'] && check_perms('users_edit_reset_keys')) {
    $DB->query("
		UPDATE users_info
		SET RatioWatchEnds = '0000-00-00 00:00:00', RatioWatchDownload = '0', RatioWatchTimes = '0'
		WHERE UserID = '$UserID'");
    $EditSummary[] = 'RatioWatch history reset';
}

if ($_POST['ResetIPHistory'] && check_perms('users_edit_reset_keys')) {

    $DB->query("
		DELETE FROM users_history_ips
		WHERE UserID = '$UserID'");
    $DB->query("
		UPDATE users_main
		SET IP = '127.0.0.1'
		WHERE ID = '$UserID'");
    $DB->query("
		UPDATE xbt_snatched
		SET IP = ''
		WHERE uid = '$UserID'");
    $DB->query("
		UPDATE users_history_passwords
		SET ChangerIP = ''
		WHERE UserID = $UserID");
    $DB->query("
		UPDATE users_history_passkeys
		SET ChangerIP = ''
		WHERE UserID = $UserID");
    $DB->query("
		UPDATE users_sessions
		SET IP = '127.0.0.1'
		WHERE UserID = $UserID");

    $EditSummary[] = 'IP history cleared';
}

if ($_POST['ResetEmailHistory'] && check_perms('users_edit_reset_keys')) {
    $DB->query("
		DELETE FROM users_history_emails
		WHERE UserID = '$UserID'");
    if ($_POST['ResetIPHistory']) {
        $DB->query("
			INSERT INTO users_history_emails
				(UserID, Email, Time, IP)
			VALUES
				('$UserID', '$Username@" . CONFIG['SITE_HOST'] . "', '0000-00-00 00:00:00', '127.0.0.1')");
    } else {
        $DB->query("
			INSERT INTO users_history_emails
				(UserID, Email, Time, IP)
			VALUES
				('$UserID', '$Username@" . CONFIG['SITE_HOST'] . "', '0000-00-00 00:00:00', '" . $Cur['IP'] . "')");
    }
    $DB->query("
		UPDATE users_main
		SET Email = '$Username@" . CONFIG['SITE_HOST'] . "'
		WHERE ID = '$UserID'");
    $DB->query("
		UPDATE users_history_invites AS uhi
		JOIN users_main um ON um.ID = uhi.InvitedID
		  AND (
		    um.IP IN ('127.0.0.1', '0.0.0.0')
		    OR um.Visible = '0'
		  )
		SET uhi.Email = '$Username@" . CONFIG['SITE_HOST'] . "'
		WHERE uhi.Email != um.Email
			AND uhi.InvitedID = '$UserID'");

    $EditSummary[] = 'Email history cleared';
}

if ($_POST['ResetSnatchList'] && check_perms('users_edit_reset_keys')) {
    $DB->query("
		DELETE FROM xbt_snatched
		WHERE uid = '$UserID'");
    $EditSummary[] = 'Snatch list cleared';
    $Cache->delete_value("recent_snatches_$UserID");
}

if ($_POST['ResetDownloadList'] && check_perms('users_edit_reset_keys')) {
    $DB->query("
		DELETE FROM users_downloads
		WHERE UserID = '$UserID'");
    $EditSummary[] = 'Download list cleared';
}

if (($_POST['ResetSession'] || $_POST['LogOut']) && check_perms('users_logout')) {
    $Cache->delete_value("user_info_$UserID");
    $Cache->delete_value("user_info_heavy_$UserID");
    $Cache->delete_value("user_stats_$UserID");
    $Cache->delete_value("enabled_$UserID");

    if ($_POST['LogOut']) {
        $DB->query("
			SELECT SessionID
			FROM users_sessions
			WHERE UserID = '$UserID'");
        while (list($SessionID) = $DB->next_record()) {
            $Cache->delete_value("session_{$UserID}_$SessionID");
        }
        $Cache->delete_value("users_sessions_$UserID");

        $DB->query("
			DELETE FROM users_sessions
			WHERE UserID = '$UserID'");
    }
}

// Start building SQL query and edit summary
if (
    $Classes[$Class]['Level'] != $Cur['Class']
    && (
        ($Classes[$Class]['Level'] < $LoggedUser['Class'] && check_perms('users_promote_below', $Cur['Class']))
        || ($Classes[$Class]['Level'] <= $LoggedUser['Class'] && check_perms('users_promote_to', $Cur['Class'] - 1)))
) {
    $UpdateSet[] = "PermissionID = '$Class'";
    $EditSummary[] = 'class changed to ' . Users::make_class_string($Class);
    $LightUpdates['PermissionID'] = $Class;
    $DeleteKeys = true;

    $DB->query("
		SELECT DISTINCT DisplayStaff
		FROM permissions
		WHERE ID = $Class
			OR ID = " . $ClassLevels[$Cur['Class']]['ID']);
    if ($DB->record_count() === 2) {
        if ($Classes[$Class]['Level'] < $Cur['Class']) {
            $SupportFor = '';
        }
        $ClearStaffIDCache = true;
    }
    $Cache->delete_value("donor_info_$UserID");
}

if ($Username !== $Cur['Username'] && check_perms('users_edit_usernames', $Cur['Class'] - 1)) {
    if (strtolower($Username) !== strtolower($Cur['Username'])) {
        $DB->prepared_query("
			SELECT ID
			FROM users_main
			WHERE Username = ?", $Username);
        if ($DB->has_results()) {
            list($UsedUsernameID) = $DB->next_record();
            error("Username already in use by <a href=\"user.php?id=$UsedUsernameID\">$Username</a>");
            header("Location: user.php?id=$UserID");
            die();
        }
    } elseif ($Username == '0' || $Username == '1') {
        error('You cannot set a username of "0" or "1".');
        header("Location: user.php?id=$UserID");
        die();
    } else {
        $UpdateSet[] = "Username = '$Username'";
        $EditSummary[] = "username changed from " . $Cur['Username'] . " to $Username";
        $LightUpdates['Username'] = $Username;
    }
}

if ($Title != db_string(display_str($Cur['Title'])) && check_perms('users_edit_titles')) {
    // Using the unescaped value for the test to avoid confusion
    if (mb_strlen($_POST['Title']) > 2048) {
        error("Custom titles have a maximum length of 2,048 characters.");
        header("Location: user.php?id=$UserID");
        die();
    } else {
        $UpdateSet[] = "Title = '$Title'";
        $EditSummary[] = "title changed to [code]{$Title}[/code]";
        $LightUpdates['Title'] = $_POST['Title'];
    }
}

if ($Donor != $Cur['Donor'] && check_perms('users_give_donor')) {
    $UpdateSet[] = "Donor = '$Donor'";
    $EditSummary[] = 'donor status changed';
    $LightUpdates['Donor'] = $Donor;
}

// Secondary classes
if (check_perms('users_promote_below') || check_perms('users_promote_to')) {
    $OldClasses = $Cur['SecondaryClasses'] ? explode(',', $Cur['SecondaryClasses']) : array();
    $DroppedClasses = array_diff($OldClasses, $SecondaryClasses);
    $AddedClasses   = array_diff($SecondaryClasses, $OldClasses);
    if (count($DroppedClasses) > 0) {
        $ClassChanges = array();
        foreach ($DroppedClasses as $PermID) {
            $ClassChanges[] = $Classes[$PermID]['Name'];
        }
        $EditSummary[] = 'Secondary classes dropped: ' . implode(', ', $ClassChanges);
        $DB->prepared_query(
            "
			DELETE FROM users_levels
			WHERE UserID = '$UserID'
				AND PermissionID IN (" . implode(', ', array_fill(0, count($DroppedClasses), '?')) . ")",
            ...$DroppedClasses
        );
        if (count($SecondaryClasses) > 0) {
            $LightUpdates['ExtraClasses'] = array_fill_keys($SecondaryClasses, 1);
        } else {
            $LightUpdates['ExtraClasses'] = array();
        }
        $DeleteKeys = true;
    }
    if (count($AddedClasses) > 0) {
        $ClassChanges = array();
        foreach ($AddedClasses as $PermID) {
            $ClassChanges[] = $Classes[$PermID]['Name'];
        }
        $EditSummary[] = "Secondary classes added: " . implode(', ', $ClassChanges);
        $Values = array();
        foreach ($AddedClasses as $PermID) {
            $Values[] = $UserID;
            $Values[] = $PermID;
        }
        if (in_array('31', $AddedClasses)) {
            Misc::send_pm_with_tpl($UserID, 'official_recruiter', ['SiteName' => CONFIG['SITE_NAME']]);
        }
        $DB->prepared_query(
            "
			INSERT INTO users_levels (UserID, PermissionID)
			VALUES " . implode(', ', array_fill(0, count($Values) / 2, '(?, ?)')),
            ...$Values
        );
        //$LightUpdates['ExtraClasses'] = array_fill_keys($SecondaryClasses, 1);
        $DeleteKeys = true;
    }
}

if ($Visible != $Cur['Visible'] && check_perms('users_make_invisible')) {
    $UpdateSet[] = "Visible = '$Visible'";
    $EditSummary[] = 'visibility changed';
    $LightUpdates['Visible'] = $Visible;
    $TrackerUserUpdates['visible'] = $Visible;
}

if ($Uploaded != $Cur['Uploaded'] && $Uploaded != $_POST['OldUploaded'] && (check_perms('users_edit_ratio')
    || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID']))) {
    $UpdateSet[] = "Uploaded = '$Uploaded'";
    $EditSummary[] = "uploaded changed from " . Format::get_size($Cur['Uploaded']) . ' to ' . Format::get_size($Uploaded);
    $Cache->delete_value("user_stats_$UserID");
}

if ($Downloaded != $Cur['Downloaded'] && $Downloaded != $_POST['OldDownloaded'] && (check_perms('users_edit_ratio')
    || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID']))) {
    $UpdateSet[] = "Downloaded = '$Downloaded'";
    $EditSummary[] = "downloaded changed from " . Format::get_size($Cur['Downloaded']) . ' to ' . Format::get_size($Downloaded);
    $Cache->delete_value("user_stats_$UserID");
}

if (
    $BonusPoints != floatval($Cur['BonusPoints']) && $BonusPoints != floatval($_POST['OldBonusPoints'])
    && (check_perms('users_edit_ratio') || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID']))
) {
    $UpdateSet[] = "BonusPoints = '{$BonusPoints}'";
    $EditSummary[] = "bonus points changed from {$Cur['BonusPoints']} to {$BonusPoints}";
    $Cache->delete_value("user_stats_{$UserID}");
}

if (
    $FLTokens != $Cur['FLTokens']
    && (check_perms('users_edit_ratio')
        || (check_perms('admin_manage_user_fls'))
        || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID']))
) {
    $UpdateSet[] = "FLTokens = $FLTokens";
    $EditSummary[] = "Freeleech Tokens changed from $Cur[FLTokens] to $FLTokens";
    $HeavyUpdates['FLTokens'] = $FLTokens;
}

if ($Invites != $Cur['Invites'] && check_perms('users_edit_invites')) {
    $UpdateSet[] = "invites = '$Invites'";
    $EditSummary[] = "number of invites changed from $Cur[Invites] to $Invites";
    $HeavyUpdates['Invites'] = $Invites;
}

if ($Warned == 1 && $Cur['Warned'] == '0000-00-00 00:00:00' && check_perms('users_warn')) {
    $Weeks = t('server.user.week', ['Count' => $WarnLength]);
    Misc::send_pm_with_tpl(
        $UserID,
        'comment_warning',
        ['Length' => $WarnLength, 'URL' => 'wiki.php?action=article&amp;name=warnings', 'PrivateMessage' => $WarnReason]
    );
    $UpdateSet[] = "Warned = '" . sqltime() . "' + INTERVAL $WarnLength WEEK";
    $Msg = "warned for $WarnLength $Weeks";
    if ($WarnReason) {
        $Msg .= " for \"$WarnReason\"";
    }
    $EditSummary[] = db_string($Msg);
    $LightUpdates['Warned'] = time_plus(3600 * 24 * 7 * $WarnLength);
} elseif ($Warned == 0 && $Cur['Warned'] != '0000-00-00 00:00:00' && check_perms('users_warn')) {
    $UpdateSet[] = "Warned = '0000-00-00 00:00:00'";
    $EditSummary[] = 'warning removed';
    $LightUpdates['Warned'] = '0000-00-00 00:00:00';
} elseif ($Warned == 1 && $ExtendWarning != '---' && check_perms('users_warn')) {
    $Weeks = 'week' . ($ExtendWarning === 1 ? '' : 's');
    Misc::send_pm_with_tpl($UserID, 'warning_extended', ['ExtendWarning' => $ExtendWarning, 'UserName' => $LoggedUser['Username'], 'WarnReason' => $WarnReason]);
    $UpdateSet[] = "Warned = Warned + INTERVAL $ExtendWarning WEEK";
    $DB->query("
		SELECT Warned + INTERVAL $ExtendWarning WEEK
		FROM users_info
		WHERE UserID = '$UserID'");
    list($WarnedUntil) = $DB->next_record();
    $Msg = "warning extended by $ExtendWarning $Weeks to $WarnedUntil";
    if ($WarnReason) {
        $Msg .= " for \"$WarnReason\"";
    }
    $EditSummary[] = db_string($Msg);
    $LightUpdates['Warned'] = $WarnedUntil;
} elseif ($Warned == 1 && $ExtendWarning == '---' && $ReduceWarning != '---' && check_perms('users_warn')) {
    $Weeks = 'week' . ($ReduceWarning === 1 ? '' : 's');
    Misc::send_pm_with_tpl($UserID, 'warning_reduced', ['ReduceWarning' => $ReduceWarning, 'UserName' => $LoggedUser['Username'], 'WarnReason' => $WarnReason]);
    $UpdateSet[] = "Warned = Warned - INTERVAL $ReduceWarning WEEK";
    $DB->query("
		SELECT Warned - INTERVAL $ReduceWarning WEEK
		FROM users_info
		WHERE UserID = '$UserID'");
    list($WarnedUntil) = $DB->next_record();
    $Msg = "warning reduced by $ReduceWarning $Weeks to $WarnedUntil";
    if ($WarnReason) {
        $Msg .= " for \"$WarnReason\"";
    }
    $EditSummary[] = db_string($Msg);
    $LightUpdates['Warned'] = $WarnedUntil;
}

if ($SupportFor != db_string($Cur['SupportFor']) && (check_perms('admin_manage_fls') || (check_perms('users_mod') && $UserID == $LoggedUser['ID']))) {
    $UpdateSet[] = "SupportFor = '$SupportFor'";
    $EditSummary[] = "First-Line Support status changed to \"$SupportFor\"";
}

if ($RestrictedForums != db_string($Cur['RestrictedForums']) && check_perms('users_mod')) {
    $UpdateSet[] = "RestrictedForums = '$RestrictedForums'";
    $EditSummary[] = "restricted forum(s): $RestrictedForums";
    $DeleteKeys = true;
}

if ($PermittedForums != db_string($Cur['PermittedForums']) && check_perms('users_mod')) {
    $ForumSet = explode(',', $PermittedForums);
    $ForumList = array();
    foreach ($ForumSet as $ForumID) {
        if ($Forums[$ForumID]['MinClassCreate'] <= $LoggedUser['EffectiveClass']) {
            $ForumList[] = $ForumID;
        }
    }
    $PermittedForums = implode(',', $ForumSet);
    $UpdateSet[] = "PermittedForums = '$PermittedForums'";
    $EditSummary[] = "permitted forum(s): $PermittedForums";
    $DeleteKeys = true;
}

if ($DisableAvatar != $Cur['DisableAvatar'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableAvatar = '$DisableAvatar'";
    $EditSummary[] = 'avatar privileges ' . ($DisableAvatar ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableAvatar'] = $DisableAvatar;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '头像', 'UserReason' => $UserReason]);
    }
}

if ($DisableLeech != $Cur['can_leech'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "can_leech = '$DisableLeech'";
    $EditSummary[] = "leeching status changed (" . translateLeechStatus($Cur['can_leech']) . " -> " . translateLeechStatus($DisableLeech) . ")";
    $HeavyUpdates['DisableLeech'] = $DisableLeech;
    $HeavyUpdates['CanLeech'] = $DisableLeech;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '下载', 'UserReason' => $UserReason]);
    }
    $TrackerUserUpdates['can_leech'] = $DisableLeech;
}

if ($DisableInvites != $Cur['DisableInvites'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableInvites = '$DisableInvites'";
    if ($DisableInvites == 1) {
        //$UpdateSet[] = "Invites = '0'";
        if (!empty($UserReason)) {
            Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '邀请', 'UserReason' => $UserReason]);
        }
    }
    $EditSummary[] = 'invites privileges ' . ($DisableInvites ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableInvites'] = $DisableInvites;
}

if ($DisableCheckAll != $Cur['DisableCheckAll'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableCheckAll = '$DisableCheckAll'";
    $HeavyUpdates['DisableCheckAll'] = $DisableCheckAll;
    $EditSummary[] = 'check all torrents privileges ' . ($DisableCheckAll ? 'disabled' : 'enabled');
}

if ($DisableCheckSelf != $Cur['DisableCheckSelf'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableCheckSelf = '$DisableCheckSelf'";
    $HeavyUpdates['DisableCheckSelf'] = $DisableCheckSelf;
    $EditSummary[] = 'check self torrents privileges ' . ($DisableCheckSelf ? 'disabled' : 'enabled');
}

if ($DisablePosting != $Cur['DisablePosting'] && check_perms('users_disable_posts')) {
    $UpdateSet[] = "DisablePosting = '$DisablePosting'";
    $EditSummary[] = 'posting privileges ' . ($DisablePosting ? 'disabled' : 'enabled');
    $HeavyUpdates['DisablePosting'] = $DisablePosting;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '论坛发帖', 'UserReason' => $UserReason]);
    }
}

if ($DisablePoints != $Cur['DisablePoints'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisablePoints = '{$DisablePoints}'";
    $EditSummary[] = 'points privileges ' . ($DisablePoints ? 'disabled' : 'enabled');
    $HeavyUpdates['DisablePoints'] = $DisablePoints;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '积分', 'UserReason' => $UserReason]);
    }
}

if ($DisableForums != $Cur['DisableForums'] && check_perms('users_disable_posts')) {
    $UpdateSet[] = "DisableForums = '$DisableForums'";
    $EditSummary[] = 'forums privileges ' . ($DisableForums ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableForums'] = $DisableForums;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '论坛访问', 'UserReason' => $UserReason]);
    }
}

if ($DisableTagging != $Cur['DisableTagging'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableTagging = '$DisableTagging'";
    $EditSummary[] = 'tagging privileges ' . ($DisableTagging ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableTagging'] = $DisableTagging;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '标签编辑', 'UserReason' => $UserReason]);
    }
}

if ($DisableUpload != $Cur['DisableUpload'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableUpload = '$DisableUpload'";
    $EditSummary[] = 'upload privileges ' . ($DisableUpload ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableUpload'] = $DisableUpload;
    if ($DisableUpload == 1) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '发布', 'UserReason' => $UserReason]);
    }
}

if ($DisableWiki != $Cur['DisableWiki'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableWiki = '$DisableWiki'";
    $EditSummary[] = 'wiki privileges ' . ($DisableWiki ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableWiki'] = $DisableWiki;
    $HeavyUpdates['site_edit_wiki'] = 0;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '站点信息', 'UserReason' => $UserReason]);
    }
}

if ($DisablePM != $Cur['DisablePM'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisablePM = '$DisablePM'";
    $EditSummary[] = 'PM privileges ' . ($DisablePM ? 'disabled' : 'enabled');
    $HeavyUpdates['DisablePM'] = $DisablePM;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '私信', 'UserReason' => $UserReason]);
    }
}

if ($DisableIRC != $Cur['DisableIRC'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableIRC = '$DisableIRC'";
    $EditSummary[] = 'IRC privileges ' . ($DisableIRC ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableIRC'] = $DisableIRC;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => 'IRC', 'UserReason' => $UserReason]);
    }
}

if ($DisableRequests != $Cur['DisableRequests'] && check_perms('users_disable_any')) {
    $UpdateSet[] = "DisableRequests = '$DisableRequests'";
    $EditSummary[] = 'request privileges ' . ($DisableRequests ? 'disabled' : 'enabled');
    $HeavyUpdates['DisableRequests'] = $DisableRequests;
    if (!empty($UserReason)) {
        Misc::send_pm_with_tpl($UserID, 'privileges_disabled', ['PrivilegeName' => '求种', 'UserReason' => $UserReason]);
    }
}


if ($EnableUser != $Cur['Enabled'] && check_perms('users_disable_users')) {
    $EnableStr = 'account ' . translateUserStatus($Cur['Enabled']) . '->' . translateUserStatus($EnableUser);
    if ($EnableUser == '2') {
        Tools::disable_users($UserID, '', 1);
        $TrackerUserUpdates = array();
    } elseif ($EnableUser == '1') {
        $Cache->increment('stats_user_count');
        $VisibleTrIP = $Visible && $Cur['IP'] != '127.0.0.1' ? '1' : '0';
        Tracker::update_tracker('add_user', array('id' => $UserID, 'passkey' => $Cur['torrent_pass'], 'visible' => $VisibleTrIP));
        if (($Cur['Downloaded'] == 0) || ($Cur['Uploaded'] / $Cur['Downloaded'] >= $Cur['RequiredRatio'])) {
            $UpdateSet[] = "i.RatioWatchEnds = '0000-00-00 00:00:00'";
            $CanLeech = 1;
            $UpdateSet[] = "m.can_leech = '1'";
            $UpdateSet[] = "i.RatioWatchDownload = '0'";
            $HeavyUpdates['DisableLeech'] = $CanLeech;
            $HeavyUpdates['CanLeech'] = $CanLeech;
        } else {
            $EnableStr .= ' (Ratio: ' . Format::get_ratio_html($Cur['Uploaded'], $Cur['Downloaded'], false) . ', RR: ' . number_format($Cur['RequiredRatio'], 2) . ')';
            if ($Cur['RatioWatchEnds'] != '0000-00-00 00:00:00') {
                $UpdateSet[] = "i.RatioWatchEnds = NOW()";
                $UpdateSet[] = "i.RatioWatchDownload = m.Downloaded";
                $CanLeech = 0;
            }
            $TrackerUserUpdates['can_leech'] = 0;
        }
        $UpdateSet[] = "i.BanReason = '0'";
        $UpdateSet[] = "Enabled = '1'";
        $LightUpdates['Enabled'] = 1;
    }
    $EditSummary[] = $EnableStr;
    $Cache->replace_value("enabled_$UserID", $EnableUser, 0);
}

if ($ResetPasskey == 1 && check_perms('users_edit_reset_keys')) {
    $Passkey = db_string(Users::make_secret());
    $UpdateSet[] = "torrent_pass = '$Passkey'";
    $EditSummary[] = 'passkey reset';
    $HeavyUpdates['torrent_pass'] = $Passkey;
    $TrackerUserUpdates['passkey'] = $Passkey;
    $Cache->delete_value('user_' . $Cur['torrent_pass']);
    //MUST come after the case for updating can_leech.

    $DB->query("
		INSERT INTO users_history_passkeys
			(UserID, OldPassKey, NewPassKey, ChangerIP, ChangeTime)
		VALUES
			('$UserID', '" . $Cur['torrent_pass'] . "', '$Passkey', '0.0.0.0', '" . sqltime() . "')");
    Tracker::update_tracker('change_passkey', array('oldpasskey' => $Cur['torrent_pass'], 'newpasskey' => $Passkey));
}

if ($ResetAuthkey == 1 && check_perms('users_edit_reset_keys')) {
    $Authkey = db_string(Users::make_secret());
    $UpdateSet[] = "AuthKey = '$Authkey'";
    $EditSummary[] = 'authkey reset';
    $HeavyUpdates['AuthKey'] = $Authkey;
}

if ($SendHackedMail && check_perms('users_disable_any')) {
    $EditSummary[] = "hacked account email sent to $HackedEmail";
    // TODO by qwerty 补充send email 邮件内容
}

if ($MergeStatsFrom && check_perms('users_edit_ratio')) {
    $DB->query("
		SELECT ID, Uploaded, Downloaded
		FROM users_main
		WHERE Username LIKE '$MergeStatsFrom'");
    if ($DB->has_results()) {
        list($MergeID, $MergeUploaded, $MergeDownloaded) = $DB->next_record();
        $DB->query("
			UPDATE users_main AS um
				JOIN users_info AS ui ON um.ID = ui.UserID
			SET
				um.Uploaded = 0,
				um.Downloaded = 0,
				ui.AdminComment = CONCAT('" . sqltime() . ' - Stats (Uploaded: ' . Format::get_size($MergeUploaded) . ', Downloaded: ' . Format::get_size($MergeDownloaded) . ', Ratio: ' . Format::get_ratio($MergeUploaded, $MergeDownloaded) . ') merged into ' . site_url() . "user.php?id=$UserID (" . $Cur['Username'] . ') by ' . $LoggedUser['Username'] . "\n\n', ui.AdminComment)
			WHERE ID = $MergeID");
        $UpdateSet[] = "Uploaded = Uploaded + '$MergeUploaded'";
        $UpdateSet[] = "Downloaded = Downloaded + '$MergeDownloaded'";
        $EditSummary[] = 'stats merged from ' . site_url() . "user.php?id=$MergeID ($MergeStatsFrom) (previous stats: Uploaded: " . Format::get_size($Cur['Uploaded']) . ', Downloaded: ' . Format::get_size($Cur['Downloaded']) . ', Ratio: ' . Format::get_ratio($Cur['Uploaded'], $Cur['Downloaded']) . ')';
        $Cache->delete_value("user_stats_$UserID");
        $Cache->delete_value("user_stats_$MergeID");
    }
}

if ($Pass && check_perms('users_edit_password')) {
    $UpdateSet[] = "PassHash = '" . db_string(Users::make_password_hash($Pass)) . "'";
    $EditSummary[] = 'password reset';

    $Cache->delete_value("user_info_$UserID");
    $Cache->delete_value("user_info_heavy_$UserID");
    $Cache->delete_value("user_stats_$UserID");
    $Cache->delete_value("enabled_$UserID");

    $DB->query("
		SELECT SessionID
		FROM users_sessions
		WHERE UserID = '$UserID'");
    while (list($SessionID) = $DB->next_record()) {
        $Cache->delete_value("session_{$UserID}_$SessionID");
    }
    $Cache->delete_value("users_sessions_$UserID");

    $DB->query("
		DELETE FROM users_sessions
		WHERE UserID = '$UserID'");
}

if (empty($UpdateSet) && empty($EditSummary)) {
    header("Location: user.php?id=$UserID");
}

if (count($TrackerUserUpdates) > 1) {
    Tracker::update_tracker('update_user', $TrackerUserUpdates);
}

if ($DeleteKeys) {
    $Cache->delete_value("user_info_$UserID");
    $Cache->delete_value("user_info_heavy_$UserID");
} else {
    $Cache->begin_transaction("user_info_$UserID");
    $Cache->update_row(false, $LightUpdates);
    $Cache->commit_transaction(0);

    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, $HeavyUpdates);
    $Cache->commit_transaction(0);
}

$Summary = '';
// Create edit summary
if ($EditSummary || $Reason) {
    if ($EditSummary) {
        $Summary = implode(', ', $EditSummary) . ' by ' . $LoggedUser['Username'];
        $Summary = sqltime() . ' - ' . ucfirst($Summary);

        if ($Reason) {
            $Summary .= "\nReason: $Reason";
        }
    } else {
        $Summary .=  sqltime() . ' - ' . "Comment: " . $Reason . " by " . $LoggedUser['Username'];
    }
    $AdminComment = $Cur['AdminComment'];
    $Summary .= "\n\n$AdminComment";
}

if (!empty($Summary)) {
    $UpdateSet[] = "AdminComment = '$Summary'";
}
// Update cache


// Build query

$SET = implode(', ', $UpdateSet);

$SQL = "
	UPDATE users_main AS m
		JOIN users_info AS i ON m.ID = i.UserID
	SET $SET
	WHERE m.ID = '$UserID'";

// Perform update
//die($SQL);
$DB->query($SQL);

if (isset($ClearStaffIDCache)) {
    $Cache->delete_value('staff_ids');
}

// redirect to user page
header("location: user.php?id=$UserID");

function translateUserStatus($Status) {
    switch ($Status) {
        case 0:
            return 'Unconfirmed';
        case 1:
            return 'Enabled';
        case 2:
            return 'Disabled';
        default:
            return $Status;
    }
}

function translateLeechStatus($Status) {
    switch ($Status) {
        case 0:
            return 'Disabled';
        case 1:
            return 'Enabled';
        default:
            return $Status;
    }
}
