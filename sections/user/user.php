<?

use Gazelle\Manager\Donation;

include(CONFIG['SERVER_ROOT'] . '/classes/torrenttable.class.php');

if (empty($_GET['id']) || !is_number($_GET['id']) || (!empty($_GET['preview']) && !is_number($_GET['preview']))) {
    error(404);
}
$UserID = (int)$_GET['id'];
$Bonus = new \Gazelle\Bonus($DB, $Cache);

if (!empty($_POST)) {
    authorize();
    foreach (['action', 'flsubmit', 'fltype'] as $arg) {
        if (!isset($_POST[$arg])) {
            error(403);
        }
    }
    if ($_POST['action'] !== 'fltoken') {
        error(403);
    }
    if ($_POST['flsubmit'] !== 'Send') {
        error(403);
    }
    if (!preg_match('/^fl-(other-[123])$/', $_POST['fltype'], $match)) {
        error(403);
    }
    $FL_OTHER_tokens = $Bonus->purchaseTokenOther($LoggedUser['ID'], $UserID, $match[1], $LoggedUser);
    echo json_encode(array("tokens" => $FL_OTHER_tokens));
    return;
}
$Preview = isset($_GET['preview']) ? $_GET['preview'] : 0;
if ($UserID == $LoggedUser['ID']) {
    $OwnProfile = true;
    if ($Preview == 1) {
        $OwnProfile = false;
        $ParanoiaString = $_GET['paranoia'];
        $CustomParanoia = explode(',', $ParanoiaString);
    }
    $FL_Items = [];
} else {
    $OwnProfile = false;
    //Don't allow any kind of previewing on others' profiles
    $Preview = 0;
    $FL_Items = $Bonus->getListOther($UserID, G::$LoggedUser['BonusPoints']);
}

// 捐赠信息
$donation = new Donation();
$donationInfo = $donation->info($UserID);
$leaderBoardRank = $donation->leaderboardRank($UserID);
$donorVisible = $donation->isVisible($UserID);
$isDonor = $donation->isDonor($UserID);
$EnableRewards = $donation->enabledRewards($UserID);
$ProfileRewards = $donation->profileRewards($UserID);
$donationHistroy = $donation->history($UserID);
$DonorRewards = $donation->rewards($UserID);
$EnabledRewards = $donation->enabledRewards($UserID);

$FA_Key = null;

if (check_perms('users_mod')) { // Person viewing is a staff member
    $DB->query("
		SELECT
			m.Username,
			m.Email,
			m.LastAccess,
			m.IP,
			p.Level AS Class,
			m.Uploaded,
			m.Downloaded,
			m.BonusPoints,
			m.RequiredRatio,
			m.Title,
			m.torrent_pass,
			m.Enabled,
			m.Paranoia,
			m.Invites,
			m.can_leech,
			m.Visible,
			i.JoinDate,
			i.Info,
			i.Avatar,
			i.AdminComment,
			i.Donor,
			i.Found,
			i.Artist,
			i.Warned,
			i.SupportFor,
			i.RestrictedForums,
			i.PermittedForums,
			i.Inviter,
			inviter.Username,
			COUNT(DISTINCT posts.id) AS ForumPosts,
			i.RatioWatchEnds,
			i.RatioWatchDownload,
			i.DisableAvatar,
			i.DisableInvites,
			i.DisablePosting,
			i.DisablePoints,
			i.DisableForums,
			i.DisableTagging,
			i.DisableUpload,
			i.DisableWiki,
			i.DisablePM,
			i.DisableIRC,
			i.DisableRequests," . "
			m.FLTokens,
			m.2FA_Key,
			SHA1(i.AdminComment),
			i.InfoTitle,
			la.Type AS LockedAccount,
			i.DisableCheckAll,
			i.DisableCheckSelf,
			m.TotalUploads,
            m.BonusUploaded,
            COUNT(DISTINCT t.ID) AS Uploads
		FROM users_main AS m
			JOIN users_info AS i ON i.UserID = m.ID
			LEFT JOIN users_main AS inviter ON i.Inviter = inviter.ID
			LEFT JOIN permissions AS p ON p.ID = m.PermissionID
			LEFT JOIN forums_posts AS posts ON posts.AuthorID = m.ID
			LEFT JOIN locked_accounts AS la ON la.UserID = m.ID
            LEFT JOIN torrents AS t ON t.UserID = m.ID
		WHERE m.ID = '$UserID'
		GROUP BY AuthorID");

    if (!$DB->has_results()) { // If user doesn't exist
        header("Location: log.php?search=User+$UserID");
    }

    list($Username, $Email, $LastAccess, $IP, $Class, $Uploaded, $Downloaded, $BonusPoints, $RequiredRatio, $CustomTitle, $torrent_pass, $Enabled, $Paranoia, $Invites, $DisableLeech, $Visible, $JoinDate, $Info, $Avatar, $AdminComment, $Donor, $Found, $Artist, $Warned, $SupportFor, $RestrictedForums, $PermittedForums, $InviterID, $InviterName, $ForumPosts, $RatioWatchEnds, $RatioWatchDownload, $DisableAvatar, $DisableInvites, $DisablePosting, $DisablePoints, $DisableForums, $DisableTagging, $DisableUpload, $DisableWiki, $DisablePM, $DisableIRC, $DisableRequests, $FLTokens, $FA_Key, $CommentHash, $InfoTitle, $LockedAccount, $DisableCheckAll, $DisableCheckSelf, $TotalUploads, $BonusUploaded, $Uploads) = $DB->next_record(MYSQLI_NUM, array(9, 12));
} else { // Person viewing is a normal user
    $DB->query("
		SELECT
			m.Username,
			m.Email,
			m.LastAccess,
			m.IP,
			p.Level AS Class,
			m.Uploaded,
			m.Downloaded,
			m.BonusPoints,
			m.RequiredRatio,
			m.Enabled,
			m.Paranoia,
			m.Invites,
			m.Title,
			m.torrent_pass,
			m.can_leech,
			i.JoinDate,
			i.Info,
			i.Avatar,
			m.FLTokens,
			i.Donor,
			i.Found,
			i.Warned,
			COUNT(DISTINCT posts.id) AS ForumPosts,
			i.Inviter,
			i.DisableInvites,
			inviter.username,
			i.InfoTitle,
            m.BonusUploaded,
            m.TotalUploads,
            COUNT(DISTINCT t.ID) AS Uploads
		FROM users_main AS m
			JOIN users_info AS i ON i.UserID = m.ID
			LEFT JOIN permissions AS p ON p.ID = m.PermissionID
			LEFT JOIN users_main AS inviter ON i.Inviter = inviter.ID
			LEFT JOIN forums_posts AS posts ON posts.AuthorID = m.ID
            LEFT JOIN torrents AS t ON t.UserID = m.ID
		WHERE m.ID = $UserID
		GROUP BY AuthorID");

    if (!$DB->has_results()) { // If user doesn't exist
        header("Location: log.php?search=User+$UserID");
    }

    list(
        $Username, $Email, $LastAccess, $IP, $Class, $Uploaded, $Downloaded, $BonusPoints,
        $RequiredRatio, $Enabled, $Paranoia, $Invites, $CustomTitle, $torrent_pass,
        $DisableLeech, $JoinDate, $Info, $Avatar, $FLTokens, $Donor, $Found, $Warned,
        $ForumPosts, $InviterID, $DisableInvites, $InviterName, $InfoTitle, $BonusUploaded, $TotalUploads, $Uploads
    ) = $DB->next_record(MYSQLI_NUM, array(10, 12));
}

$DB->query("
SELECT
	IFNULL(SUM(t.Size / (1024 * 1024 * 1024) * 1 *(
		0.025 + (
			(0.06 * LN(1 + (xfh.seedtime / (24)))) / (POW(GREATEST(t.Seeders, 1), 0.6))
		)
	)),0)
FROM
	(SELECT DISTINCT uid,fid FROM xbt_files_users WHERE active=1 AND remaining=0 AND mtime > unix_timestamp(NOW() - INTERVAL 1 HOUR) AND uid = {$UserID}) AS xfu
	JOIN xbt_files_history AS xfh ON xfh.uid = xfu.uid AND xfh.fid = xfu.fid
	JOIN torrents AS t ON t.ID = xfu.fid
WHERE
	xfu.uid = {$UserID}");
list($BonusPointsPerHour) = $DB->next_record(MYSQLI_NUM);

// Image proxy CTs
$DisplayCustomTitle = $CustomTitle;
if (check_perms('site_proxy_images') && !empty($CustomTitle)) {
    $DisplayCustomTitle = preg_replace_callback(
        '~src=("?)(http.+?)(["\s>])~',
        function ($Matches) {
            return 'src=' . $Matches[1] . ImageTools::process($Matches[2]) . $Matches[3];
        },
        $CustomTitle
    );
}

if ($Preview == 1) {
    if (strlen($ParanoiaString) == 0) {
        $Paranoia = array();
    } else {
        $Paranoia = $CustomParanoia;
    }
} else {
    $Paranoia = unserialize($Paranoia);
    if (!is_array($Paranoia)) {
        $Paranoia = array();
    }
}
$ParanoiaLevel = 0;
foreach ($Paranoia as $P) {
    $ParanoiaLevel++;
    if (strpos($P, '+') !== false) {
        $ParanoiaLevel++;
    }
}

$JoinDateHtml = time_diff($JoinDate);
$LastAccessHtml = time_diff($LastAccess);

function check_paranoia_here($Setting) {
    global $Paranoia, $Class, $UserID, $Preview;
    if ($Preview == 1) {
        return check_paranoia($Setting, $Paranoia, $Class);
    } else {
        return check_paranoia($Setting, $Paranoia, $Class, $UserID);
    }
}

View::show_header($Username, "jquery.imagesloaded,jquery.wookmark,user,bbcode,comments,info_paster,tiles", "PageUserShow");

if (check_paranoia_here('artistsadded')) {
    $DB->query("
		SELECT COUNT(ArtistID)
		FROM torrents_artists
		WHERE UserID = $UserID");
    list($ArtistsAdded) = $DB->next_record();
} else {
    $ArtistsAdded = 0;
}

if (check_paranoia_here('requestsvoted_count') || check_paranoia_here('requestsvoted_bounty')) {
    $DB->query("
		SELECT COUNT(RequestID), SUM(Bounty)
		FROM requests_votes
		WHERE UserID = $UserID");
    list($RequestsVoted, $TotalSpent) = $DB->next_record();
    $DB->query("
		SELECT COUNT(r.ID), SUM(rv.Bounty)
		FROM requests AS r
			LEFT JOIN requests_votes AS rv ON rv.RequestID = r.ID AND rv.UserID = r.UserID
		WHERE r.UserID = $UserID");
    list($RequestsCreated, $RequestsCreatedSpent) = $DB->next_record();
} else {
    $RequestsVoted = $TotalSpent = $RequestsCreated = $RequestsCreatedSpent = 0;
}


if (check_paranoia_here('requestsfilled_count') || check_paranoia_here('requestsfilled_bounty')) {
    $DB->query("
		SELECT
			COUNT(DISTINCT r.ID),
			SUM(rv.Bounty)
		FROM requests AS r
			LEFT JOIN requests_votes AS rv ON r.ID = rv.RequestID
		WHERE r.FillerID = $UserID");
    list($RequestsFilled, $TotalBounty) = $DB->next_record();
} else {
    $RequestsFilled = $TotalBounty = 0;
}

//Do the ranks
$UploadedRank = UserRank::get_rank('uploaded', $Uploaded);
$DownloadedRank = UserRank::get_rank('downloaded', $Downloaded);
$UploadsRank = UserRank::get_rank('uploads', $Uploads);
$RequestRank = UserRank::get_rank('requests', $RequestsFilled);
$PostRank = UserRank::get_rank('posts', $ForumPosts);
$BountyRank = UserRank::get_rank('bounty', $TotalSpent);
$ArtistsRank = UserRank::get_rank('artists', $ArtistsAdded);

if ($Downloaded == 0) {
    $Ratio = 1;
} elseif ($Uploaded == 0) {
    $Ratio = 0.5;
} else {
    $Ratio = round($Uploaded / $Downloaded, 2);
}

$OverallRank = UserRank::overall_score($UploadedRank, $DownloadedRank, $UploadsRank, $RequestRank, $PostRank, $BountyRank, $ArtistsRank, $Ratio);

$DB->query("select count(1) from thumb where ToUserID = $UserID");
list($ThumbCount) = $DB->next_record();
?>
<div class="LayoutBody">
    <div class="BodyHeader">
        <h2 class="BodyHeader-nav"><?= Users::format_username($UserID, true, true, true, false, true, false, true) ?>
            <span class="floatright" id="thumb"><?= icon("Common/like") ?><?= $ThumbCount ? ' ' . $ThumbCount : '' ?></span>
        </h2>
    </div>
    <div class="BodyNavLinks">
        <? if (!$OwnProfile) { ?>
            <a href="inbox.php?action=compose&amp;to=<?= $UserID ?>"><?= t('server.user.compose') ?></a>
            <?
            $DB->query("
		SELECT FriendID
		FROM friends
		WHERE UserID = '$LoggedUser[ID]'
			AND FriendID = '$UserID'");
            if (!$DB->has_results()) {
            ?>
                <a href="friends.php?action=add&amp;friendid=<?= $UserID ?>&amp;auth=<?= $LoggedUser['AuthKey'] ?>"><?= t('server.user.add_friend') ?></a>
            <?  } ?>
            <a href="reports.php?action=report&amp;type=user&amp;id=<?= $UserID ?>"><?= t('server.user.report') ?></a>
        <? } ?>
        <? if (check_perms('users_edit_profiles', $Class) || $LoggedUser['ID'] == $UserID) { ?>
            <a href="user.php?action=edit&amp;userid=<?= $UserID ?>"><?= t('server.user.setting') ?></a>
        <? } ?>
        <? if ($OwnProfile) { ?>
            <a class="brackets" href="bonus.php?action=bprates"><?= t('server.bonus.bonus_point_rates') ?></a>
        <? } ?>
        <? if (check_perms('users_view_invites', $Class)) { ?>
            <a href="user.php?action=invite&amp;userid=<?= $UserID ?>"><?= t('server.user.invite') ?></a>
        <? }
        if (check_perms('admin_manage_permissions', $Class)) { ?>
            <a href="user.php?action=permissions&amp;userid=<?= $UserID ?>"><?= t('server.user.permissions') ?></a>
        <? } ?>
        <? if (check_perms('users_view_ips', $Class)) { ?>
            <a href="user.php?action=sessions&amp;userid=<?= $UserID ?>"><?= t('server.user.sessions') ?></a>
            <a href="userhistory.php?action=copypaste&amp;userid=<?= $UserID ?>"><?= t('server.user.copypaste') ?></a>
        <? } ?>
        <? if (check_perms('admin_reports')) { ?>
            <a href="reportsv2.php?view=reporter&amp;id=<?= $UserID ?>"><?= t('server.user.reporter') ?></a>
        <? } ?>
        <? if (check_perms('users_mod')) { ?>
            <a href="userhistory.php?action=token_history&amp;userid=<?= $UserID ?>"><?= t('server.user.token_history') ?></a>
        <? } ?>
        <? if (check_perms('admin_clear_cache') && check_perms('users_override_paranoia')) { ?>
            <a href="user.php?action=clearcache&amp;id=<?= $UserID ?>"><?= t('server.user.clearcache') ?></a>
        <? } ?>
        <? if (check_perms('users_mod')) { ?>
            <a href="#staff_tools"><?= t('server.user.staff_tools') ?></a>
        <? } ?>
    </div>
    <div class="LayoutMainSidebar">
        <div class="Sidebar LayoutMainSidebar-sidebar">
            <?
            if ($Avatar && Users::has_avatars_enabled()) {
            ?>
                <div class="SidebarItemUserAvatar SidebarItem Box">
                    <div class="SidebarItem-header Box-header">
                        <?= t('server.user.avatar') ?>
                    </div>
                    <div class="SidebarItem-body Box-body">
                        <?= Users::show_avatar($Avatar, $UserID, $Username, $HeavyInfo['DisableAvatars'], 240) ?>
                    </div>
                </div>
            <?
            }
            if ($Enabled == 1 && (count($FL_Items) || isset($FL_OTHER_tokens))) {
            ?>
                <div class="SidebarItemUserFreeTokens SidebarItem Box">
                    <?
                    if (isset($FL_OTHER_tokens)) {
                    ?>
                        <div class="SidebarItem-header Box-header">Freeleech Tokens Given</div>
                        <ul class="Sidebar-List SidebarItem-body Box-body">
                            <?
                            if ($FL_OTHER_tokens > 0) {
                                $s = $FL_OTHER_tokens > 1 ? 's' : '';
                            ?>
                                <li class="Sidebar-item">You gave <?= $FL_OTHER_tokens ?> token<?= $s ?> to <?= $Username ?>. Your generosity is most appreciated!</li>
                            <?
                            } else {
                            ?>
                                <li class="Sidebar-item">You attempted to give some tokens to <?= $Username ?> but something didn't work out.
                                    No points were spent.</li>
                            <?
                            }
                            ?>
                        </ul>
                    <?
                    } else {
                    ?>
                        <div class="SidebarItem-header Box-header">
                            <?= t('server.user.send_fltoken') ?></div>
                        <form class="SidebarItem-body Box-body fl_form" name="user" id="fl_form" action="user.php?id=<?= $UserID ?>" method="post">
                            <ul class="SidebarList SidebarItem-body Box-body">
                                <?
                                foreach ($FL_Items as $data) {
                                    $label_title = t('server.user.this_costs', ['Values' => [$data['Price'], $data['After']]]);
                                ?>
                                    <li class="SidebarList-item">
                                        <input type="radio" name="fltype" id="fl-<?= $data['Label'] ?>" value="fl-<?= $data['Label'] ?>" />
                                        <label data-tooltip="<?= $label_title ?>" for="fl-<?= $data['Label'] ?>"> <?= $data['Name'] ?></label>
                                    </li>
                                <?
                                }
                                ?>
                                <li class="SidebarList-item"><input class="Button" type="button" name="flsubmit" onclick="sendTokens(<?= $UserID ?>, '<?= $LoggedUser['AuthKey'] ?>')" value="Send" /></li>
                            </ul>
                            <input type="hidden" name="action" value="fltoken" />
                            <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                        </form>
                    <?
                    }
                    ?>
                </div>
            <? } ?>

            <?
            $OverrideClass = $Override === 2 ? 'paranoia_override' : '';
            ?>
            <div class="SidebarItemStats SidebarItem Box">
                <div class="SidebarItem-header Box-header"><?= t('server.user.statistics') ?></div>
                <ul class="SidebarList SidebarItem-body Box-body">
                    <li class="SidebarList-item is-joinDate" id="join-date-value" data-value="<?= (new DateTime($JoinDate))->format(DateTime::ATOM) ?>">
                        <span><?= t('server.user.joineddate') ?>: </span>
                        <?= $JoinDateHtml ?>
                    </li>
                    <? if (($Override = check_paranoia_here('lastseen'))) { ?>
                        <li class="SidebarList-item is-lastAccessDate <?= $OverrideClass ?>" id="last-access-date-value" data-value="<?= (new DateTime($LastAccess))->format(DateTime::ATOM)  ?>">
                            <span><?= t('server.user.lastaccess') ?>: </span>
                            <?= $LastAccessHtml ?>
                        </li>
                    <? } ?>
                    <? if (($Override = check_paranoia_here('uploaded'))) { ?>
                        <li class="SidebarList-item is-uploaded <?= $OverrideClass ?>" id="uploaded-value" data-value="<?= $Uploaded ?>">
                            <span><?= t('server.user.uploaded') ?>: </span>
                            <span data-tooltip="<?= Format::get_size($Uploaded, 5) ?>">
                                <?= Format::get_size($Uploaded) ?>
                            </span>
                            <span data-tooltip="<?= t('server.user.true_uploaded_title') ?>">
                                (<?= Format::get_size($Uploaded - $BonusUploaded) ?>)
                            </span>
                        </li>
                    <? } ?>
                    <?
                    if (($Override = check_paranoia_here('downloaded'))) {
                        $DB->query("SELECT (SELECT IFNULL(sum(`Downloaded`), 0) FROM `users_freeleeches` WHERE `UserID`=$UserID)+(SELECT IFNULL(sum(`Downloaded`), 0) FROM `users_freetorrents` where `UserID`=$UserID)");
                        list($AllDownloaded) = $DB->next_record();
                        $AllDownloaded += $Downloaded;
                    ?>
                        <li class="SidebarList-item is-downloaded <?= $OverrideClass ?>" id="downloaded-value" data-value="<?= $Downloaded ?>">
                            <span><?= t('server.user.downloaded') ?>: </span>
                            <span data-tooltip="<?= Format::get_size($Downloaded, 5) ?>">
                                <?= Format::get_size($Downloaded) ?>
                            </span>
                            <span data-tooltip="<?= t('server.user.true_downloaded_title') ?>">
                                (<?= Format::get_size($AllDownloaded) ?>)
                            </span>
                        </li>
                    <?
                    }
                    if (($Override = (check_paranoia_here('uploaded') && check_paranoia_here('downloaded')))) {
                    ?>
                        <? $Buffer = $Uploaded / 0.65 - $Downloaded ?>
                        <li class="SidebarList-item is-buffer <?= $OverrideClass ?>" id="buffer-value" data-value="<?= $Buffer ?>" data-tooltip="<?= Format::get_size($Buffer) ?>">
                            <span><?= t('server.user.buffer') ?>: </span>
                            <?= Format::get_size($Buffer) ?>
                        </li>
                    <?
                    }
                    if (($Override = check_paranoia_here('ratio'))) {
                    ?>
                        <li class="SidebarList-item is-ratio <?= $OverrideClass ?>" id="ratio-value" data-value="<?= Format::get_ratio($Uploaded, $Downloaded) ?>">
                            <span><?= t('server.user.ratio') ?>: </span>
                            <?= Format::get_ratio_html($Uploaded, $Downloaded) ?>
                        </li>
                    <?
                    }
                    if (($Override = check_paranoia_here('requiredratio')) && isset($RequiredRatio)) {
                    ?>
                        <? $RequiredRatioDisplay = number_format((float)$RequiredRatio, 2) ?>
                        <li class="SidebarList-item is-requiredRatio <?= $OverrideClass ?>" id="required-ratio-value" data-value="<?= $RequiredRatio ?>">
                            <span><?= t('server.user.required_ratio') ?>: </span>
                            <span data-tooltip="<?= $RequiredRatioDisplay ?>">
                                <?= $RequiredRatioDisplay ?>
                            </span>
                        </li>
                    <?
                    }
                    if (($Override = check_paranoia_here('bonuspoints')) && isset($BonusPoints)) {
                    ?>
                        <li class="SidebarList-item is-bp <?= $OverrideClass ?>" id="bp-value" data-value="<?= $BonusPoints ?>">
                            <span><?= t('server.user.bonus_points') ?>: </span>
                            <?= number_format($BonusPoints) ?>
                            <?
                            if (check_perms('admin_bp_history')) {
                                printf('<a href="bonus.php?action=history&amp;id=%d" >View</a>', $UserID);
                            }
                            ?>
                        </li>
                        <li class="SidebarList-item is-bpRate <?= $OverrideClass ?>" id="bp-rate-value" data-value="<?= $BonusPointsPerHour ?>">
                            <? if ($OwnProfile) { ?>
                                <a href="bonus.php?action=bprates">
                                    <?= t('server.user.bprates') ?>
                                </a>
                            <? } else if (check_perms('users_mod')) { ?>
                                <a href="bonus.php?action=bprates&userid=<?= $UserID ?>">
                                    <?= t('server.user.bprates') ?>
                                </a>
                            <? } else { ?>
                                <?= t('server.user.bprates') ?>
                            <? } ?>
                            <span>: </span>
                            <?= number_format($BonusPointsPerHour) ?>
                        </li>
                    <?php
                    }
                    if ($OwnProfile || ($Override = check_paranoia_here(false)) || check_perms('users_mod')) {
                    ?>
                        <?
                        $Title = "";
                        $DB->query("select count(*), EndTime from tokens_typed where UserID=" . $UserID . " and Type='time' group by EndTime");
                        $TimeAndCnts = $DB->to_array(false, MYSQLI_NUM, false);
                        $num = 0;
                        foreach ($TimeAndCnts as $TAC) {
                            if ($num != 0) $Title = "$Title\n";
                            $num += $TAC[0];
                            $Title = $Title . $TAC[0] . " (" . $TAC[1] . t('server.user.space_expired');
                        }
                        $TokensDisplay = $num == 0 ? number_format($FLTokens) : number_format($FLTokens - $num) . '+' . number_format($num);
                        ?>
                        <li class="SidebarList-item is-flTokenCount <?= $OverrideClass ?>" id="fl-token-count-value" data-value="<?= $FLTokens ?>" data-tooltip="<?= $Title ?>">
                            <a href="userhistory.php?action=token_history&amp;userid=<?= $UserID ?>">
                                <?= t('server.user.token_number') ?></a>
                            <span>: </span>
                            <?= $TokensDisplay ?>
                        </li>
                    <? } ?>
                    <? if (($OwnProfile || check_perms('users_mod')) && $Warned != '0000-00-00 00:00:00') { ?>
                        <li class="SidebarList-item is-warnedExpireDate <?= $OverrideClass ?>" id="warned-expire-date-value" data-value="<?= (new DateTime($Warned))->format(DateTime::ATOM) ?>">
                            <span><?= t('server.user.warning_expires_in') ?>: </span>
                            <?= time_diff((date('Y-m-d H:i', strtotime($Warned)))) ?>
                        </li>
                    <?  } ?>
                </ul>
            </div>
            <? if ($OwnProfile || check_perms('users_override_paranoia')) { ?>
                <?
                $DB->query("SELECT xs.uid, xs.tstamp, xs.fid, t.Size
FROM xbt_snatched AS xs left join torrents AS t ON t.ID = xs.fid
WHERE xs.uid =" . $UserID . " and xs.tstamp >= unix_timestamp(date_format(now(),'%Y-%m-01')) order by 2");
                $Requests = $DB->to_array();
                $SnatchedByUser;
                foreach ($Requests as $Request) {
                    list($userID, $Time, $TorrentID, $Size) = $Request;
                    if (!isset($SnatchedByUser[$userID][$TorrentID])) {
                        $SnatchedByUser[$userID][$TorrentID]['size'] = $Size;
                        $SnatchedByUser[$userID][$TorrentID]['free'] = 0;
                        $SnatchedByUser[$userID][$TorrentID]['unfree'] = 0;
                    }
                    $SnatchedByUser[$userID][$TorrentID]['time'][] = $Time;
                }
                $DB->query("SELECT `UserID`, `TorrentID`, unix_timestamp(`Time`)
                FROM `users_freeleeches_time`
                WHERE UserID=$UserID and unix_timestamp(Time) >= unix_timestamp(date_format(now(),'%Y-%m-01')) order by 3");
                $Requests = $DB->to_array();
                $TokenByUser;
                foreach ($Requests as $Request) {
                    list($UserID, $TorrentID, $Time) = $Request;
                    $TokenByUser[$UserID][$TorrentID][] = $Time;
                }
                $UsersCnt;
                foreach ($SnatchedByUser as $UserID => &$Torrents) {
                    $UsersCnt[$UserID]['size'] = 0;
                    $UsersCnt[$UserID]['cnt'] = 0;
                    foreach ($Torrents as $TorrentID => &$Torrent) {
                        if (isset($TokenByUser[$UserID][$TorrentID])) {
                            foreach ($Torrent['time'] as $Time) {
                                $free = false;
                                foreach ($TokenByUser[$UserID][$TorrentID] as $key => $TokenTime) {
                                    if ($Time < $TokenTime + 345600) {
                                        unset($TokenByUser[$UserID][$TorrentID][$key]);
                                        $Torrent['free'] += 1;
                                        $free = true;
                                        break;
                                    }
                                }
                                if (!$free) {
                                    $Torrent['unfree'] += 1;
                                }
                            }
                        } else {
                            $Torrent['unfree'] = 1;
                        }
                    }
                }
                $DB->query("select u.ID, u.Downloaded ND, l.Downloaded LD, TorrentCnt LT from users_main as u left join users_last_month as l on u.ID=l.ID where u.ID = $UserID");
                $Requests = $DB->to_array();
                foreach ($Requests as $User) {
                    list($ID, $ND, $LD, $LT) = $User;
                    $UsersCnt[$ID]['dt'] = $ND - $LD;
                }
                unset($Torrents, $Torrent);
                foreach ($SnatchedByUser as $UserID => $Torrents) {
                    foreach ($Torrents as $Torrent) {
                        if ($Torrent['unfree']) {
                            $UsersCnt[$UserID]['size'] += $Torrent['size'];
                            if ($Torrent['size']) {
                                $UsersCnt[$UserID]['cnt']++;
                            }
                        }
                    }
                }
                $Criteria = array();
                $Criteria[] = array('ddt' => 500 * 250 * 1024 * 1024, 'tdt' => 500, 'token' => 50, 'bonus' => 6000);
                $Criteria[] = array('ddt' => 320 * 250 * 1024 * 1024, 'tdt' => 320, 'token' => 32, 'bonus' => 2800);
                $Criteria[] = array('ddt' => 200 * 250 * 1024 * 1024, 'tdt' => 200, 'token' => 20, 'bonus' => 1300);
                $Criteria[] = array('ddt' => 120 * 250 * 1024 * 1024, 'tdt' => 120, 'token' => 12, 'bonus' => 600);
                $Criteria[] = array('ddt' => 60 * 250 * 1024 * 1024, 'tdt' => 60, 'token' => 6, 'bonus' => 240);
                $Criteria[] = array('ddt' => 25 * 250 * 1024 * 1024, 'tdt' => 25, 'token' => 2, 'bonus' => 100);
                $Criteria[] = array('ddt' => 10 * 250 * 1024 * 1024, 'tdt' => 10, 'token' => 1, 'bonus' => 0);
                foreach ($UsersCnt as $UserID => $User) {
                    $LogSize = min($User['size'], $User['dt']);
                    foreach ($Criteria as $L) {
                        if ($LogSize >= $L['ddt'] && $User['cnt'] >= $L['tdt']) {
                            break;
                        }
                    }
                }
                ?>
            <? } ?>
            <div class="SidebarItemUserRank SidebarItem Box">
                <div class="SidebarItem-header Box-header">
                    <?= t('server.user.u_percentile') ?></div>
                <ul class="SidebarList SidebarItem-body Box-body">
                    <? if (($Override = check_paranoia_here('uploaded'))) { ?>
                        <li class="SidebarList-item is-uploadedRank <?= $OverrideClass ?>" id="uploaded-rank-value" data-value="<?= $UploadedRank ?>">
                            <span><?= t('server.user.u_uploaded') ?>: </span>
                            <span data-tooltip="<?= Format::get_size($Uploaded) ?>">
                                <?= $UploadedRank === false ? 'Server busy' : number_format($UploadedRank) . '%' ?>
                            </span>
                        </li>
                    <? } ?>
                    <? if (($Override = check_paranoia_here('downloaded'))) { ?>
                        <li class="SidebarList-item is-downloadedRank <?= $OverrideClass ?>" id="downloaded-rank-value" data-value="<?= $DownloadedRank ?>">
                            <span><?= t('server.user.u_downloaded') ?>: </span>
                            <span data-tooltip="<?= Format::get_size($Downloaded) ?>">
                                <?= $DownloadedRank === false ? 'Server busy' : number_format($DownloadedRank) . '%' ?>
                            </span>
                        </li>
                    <? } ?>
                    <? if (($Override = check_paranoia_here('uploads+'))) { ?>
                        <li class="SidebarList-item is-uploadsRank <?= $OverrideClass ?>" id="uploads-rank-value" data-value="<?= $UploadsRank ?>">
                            <span><?= t('server.user.u_uploads') ?>: </span>
                            <span data-tooltip="<?= number_format($Uploads) ?>">
                                <?= $UploadsRank === false ? 'Server busy' : number_format($UploadsRank) . '%' ?>
                            </span>
                        </li>
                    <? } ?>
                    <? if (($Override = check_paranoia_here('requestsfilled_count'))) { ?>
                        <li class="SidebarList-item is-requestsFilledRank <?= $OverrideClass ?>" id="requests-filled-rank-value" data-value="<?= $RequestRank ?>">
                            <span><?= t('server.user.u_filled') ?>: </span>
                            <span data-tooltip="<?= number_format($RequestsFilled) ?>">
                                <?= $RequestRank === false ? 'Server busy' : number_format($RequestRank) . '%' ?>
                            </span>
                        </li>
                    <? } ?>
                    <? if (($Override = check_paranoia_here('requestsvoted_bounty'))) { ?>
                        <li class="SidebarList-item is-bountySpentRank <?= $OverrideClass ?>" id="bounty-spent-rank-value" data-value="<?= $BountyRank ?>">
                            <span><?= t('server.user.u_bounty') ?>: </span>
                            <span data-tooltip="<?= Format::get_size($TotalSpent) ?>">
                                <?= $BountyRank === false ? 'Server busy' : number_format($BountyRank) . '%' ?>
                            </span>
                        </li>
                    <? } ?>
                    <li class="SidebarList-item is-postCreateRank" id="post-create-rank" data-value="<?= $PostRank ?>">
                        <span><?= t('server.user.u_post') ?>: </span>
                        <span data-tooltip="<?= number_format($ForumPosts) ?>">
                            <?= $PostRank === false ? 'Server busy' : number_format($PostRank) . '%' ?>
                        </span>
                    </li>
                    <? if (($Override = check_paranoia_here('artistsadded'))) { ?>
                        <li class="SidebarList-item is-artistAddRank <?= $OverrideClass ?>" id="artist-add-rank-value" data-value="<?= $ArtistsRank ?>">
                            <span><?= t('server.user.u_artist') ?>: </span>
                            <span data-tooltip="<?= number_format($ArtistsAdded) ?>">
                                <?= $ArtistsRank === false ? 'Server busy' : number_format($ArtistsRank) . '%' ?>
                            </span>
                        </li>
                    <?
                    }
                    if (check_paranoia_here(array('uploaded', 'downloaded', 'uploads+', 'requestsfilled_count', 'requestsvoted_bounty', 'artistsadded'))) { ?>
                        <li class="SidebarList-item is-overallRank" id="overall-rank-value" data-value="<?= $OverallRank ?>">
                            <span><?= t('server.user.u_total') ?>: </span>
                            <span><?= $OverallRank === false ? 'Server busy' : number_format($OverallRank) . '%' ?></span>
                        </li>
                    <?  } ?>
                </ul>
            </div>
            <?
            if ($OwnProfile || check_perms('users_override_paranoia', $Class)) {
                $DB->prepared_query("
			SELECT IRCKey
			FROM users_main
			WHERE ID = ?", $UserID);
                list($IRCKey) = $DB->next_record();
            }
            if (check_perms('users_mod', $Class) || check_perms('users_view_ips', $Class) || check_perms('users_view_keys', $Class)) {
                $DB->query("
			SELECT COUNT(*)
			FROM users_history_passwords
			WHERE UserID = '$UserID'");
                list($PasswordChanges) = $DB->next_record();
                if (check_perms('users_view_keys', $Class)) {
                    $DB->query("
				SELECT COUNT(*)
				FROM users_history_passkeys
				WHERE UserID = '$UserID'");
                    list($PasskeyChanges) = $DB->next_record();
                }
                if (check_perms('users_view_ips', $Class)) {
                    $DB->query("
				SELECT COUNT(DISTINCT IP)
				FROM users_history_ips
				WHERE UserID = '$UserID'");
                    list($IPChanges) = $DB->next_record();
                    $DB->query("
				SELECT COUNT(DISTINCT IP)
				FROM xbt_snatched
				WHERE uid = '$UserID'
					AND IP != ''");
                    list($TrackerIPs) = $DB->next_record();
                }
                if (check_perms('users_view_email', $Class)) {
                    $DB->query("
				SELECT COUNT(*)
				FROM users_history_emails
				WHERE UserID = '$UserID'");
                    list($EmailChanges) = $DB->next_record();
                }
            ?>
                <div class="SidebarItemUserHistory SidebarItem Box">
                    <div class="SidebarItem-header Box-header">
                        <?= t('server.user.history') ?></div>
                    <ul class="SidebarList SidebarItem-body Box-body">
                        <? if (check_perms('users_view_email', $Class)) { ?>
                            <li class="SidebarList-item"><?= t('server.user.emails') ?>: <?= number_format($EmailChanges) ?> <a href="userhistory.php?action=email2&amp;userid=<?= $UserID ?>"><?= t('server.user.view') ?></a><a href="userhistory.php?action=email&amp;userid=<?= $UserID ?>"> <?= t('server.user.legacy_view') ?></a></li>
                        <?
                        }
                        if (check_perms('users_view_ips', $Class)) {
                        ?>
                            <li class="SidebarList-item">IPs: <?= number_format($IPChanges) ?> <a href="userhistory.php?action=ips&amp;userid=<?= $UserID ?>"><?= t('server.user.view') ?></a><a href="userhistory.php?action=ips&amp;userid=<?= $UserID ?>&amp;usersonly=1"> <?= t('server.user.view_users') ?></a></li>
                            <? if (check_perms('users_view_ips', $Class) && check_perms('users_mod', $Class)) { ?>
                                <li class="SidebarList-item">Tracker IPs: <?= number_format($TrackerIPs) ?> <a href="userhistory.php?action=tracker_ips&amp;userid=<?= $UserID ?>"><?= t('server.user.view') ?></a></li>
                            <?
                            }
                        }
                        if (check_perms('users_view_keys', $Class)) {
                            ?>
                            <li class="SidebarList-item"><?= t('server.user.passkeys') ?>: <?= number_format($PasskeyChanges) ?> <a href="userhistory.php?action=passkeys&amp;userid=<?= $UserID ?>"><?= t('server.user.view') ?></a></li>
                        <?
                        }
                        if (check_perms('users_mod', $Class)) {
                        ?>
                            <li class="SidebarList-item"><?= t('server.user.passwords') ?>: <?= number_format($PasswordChanges) ?> <a href="userhistory.php?action=passwords&amp;userid=<?= $UserID ?>"><?= t('server.user.view') ?></a></li>
                            <li class="SidebarList-item"><?= t('server.user.stats') ?>: N/A <a href="userhistory.php?action=stats&amp;userid=<?= $UserID ?>"><?= t('server.user.view') ?></a></li>
                        <?      } ?>
                    </ul>
                </div>
            <?  } ?>
            <div class="SidebarItemUserPersonal SidebarItem Box">
                <div class="SidebarItem-header Box-header">
                    <?= t('server.user.p_personal') ?></div>
                <ul class="SidebarList SidebarItem-body Box-body">
                    <li class="SidebarList-item isClass" id="class-value" data-value="<?= $ClassLevels[$Class]['Name'] ?>">
                        <?= t('server.user.p_class') ?>: <?= $ClassLevels[$Class]['Name'] ?></li>
                    <?
                    $UserInfo = Users::user_info($UserID);
                    if (!empty($UserInfo['ExtraClasses'])) {
                    ?>
                        <li class="SidebarList-item">
                            <ul class="stats">
                                <?
                                foreach ($UserInfo['ExtraClasses'] as $PermID => $Val) {
                                ?>
                                    <li><?= $Classes[$PermID]['Name'] ?></li>
                                <?  } ?>
                            </ul>
                        </li>
                    <?
                    }
                    // An easy way for people to measure the paranoia of a user, for e.g. contest eligibility
                    if ($ParanoiaLevel == 0) {
                        $ParanoiaLevelText = 'Off';
                    } elseif ($ParanoiaLevel == 1) {
                        $ParanoiaLevelText = 'Very Low';
                    } elseif ($ParanoiaLevel <= 5) {
                        $ParanoiaLevelText = 'Low';
                    } elseif ($ParanoiaLevel <= 20) {
                        $ParanoiaLevelText = 'High';
                    } else {
                        $ParanoiaLevelText = 'Very high';
                    }
                    ?>
                    <li class="SidebarList-item"><?= t('server.user.p_paranoiaLevel') ?>: <span data-tooltip="<?= $ParanoiaLevel ?>"><?= $ParanoiaLevelText ?></span></li>
                    <? if (check_perms('users_view_invites') || $OwnProfile) {
                        if (!$InviterID) {
                            $Invited = '<span>Nobody</span>';
                        } else {
                            $Invited = "<a href=\"user.php?id=$InviterID\">$InviterName</a>";
                        } ?>
                        <li class="SidebarList-item" <?
                                                        $DB->query("select count(*), EndTime from invites_typed where UserID=" . $UserID . " and Type='time' and Used=0 group by EndTime");
                                                        $TimeAndCnts = $DB->to_array(false, MYSQLI_NUM, false);
                                                        if (count($TimeAndCnts) > 0) echo "data-tooltip=\"";
                                                        $num = 0;
                                                        foreach ($TimeAndCnts as $TAC) {
                                                            if ($num != 0) echo "\n";
                                                            $num += $TAC[0];
                                                            echo $TAC[0] . " (" . $TAC[1] . t('server.user.space_expired');
                                                        }
                                                        if ($num != 0) echo "\"";
                                                        ?>>
                            <span><?= t('server.user.p_invites') ?>: </span>
                            <?
                            $DB->query("
					SELECT COUNT(InviterID)
					FROM invites
					WHERE InviterID = '$UserID'");
                            list($Pending) = $DB->next_record();
                            if ($DisableInvites) {
                                echo 'X';
                            } else {
                                echo $num == 0 ? number_format($Invites) : number_format($Invites - $num) . '+' . number_format($num);
                            }
                            echo " ($Pending)";
                            ?>
                        </li>
                        <li class="SidebarList-item isInviter" id="inviter-value" data-value="<?= $InviterName ?>">
                            <span><?= t('server.user.p_inviter') ?>: </span>
                            <?= $Invited ?>
                        </li>
                    <? } ?>
                    <? if ((check_perms('users_view_email') && in_array("emailshowtotc", $Paranoia)) || check_perms("users_override_paranoia") || $OwnProfile) { ?>
                        <li class="SidebarList-item"><?= t('server.user.p_email') ?>: <a href="mailto:<?= display_str($Email) ?>"><?= display_str($Email) ?></a>
                            <? if (check_perms('users_view_email', $Class)) { ?>
                                <a href="user.php?action=search&amp;email_history=on&amp;email=<?= display_str($Email) ?>" data-tooltip="Search">S</a>
                            <? } ?>
                        </li>
                    <? } ?>
                    <? if (check_perms('users_view_ips', $Class)) { ?>
                        <li class="SidebarList-item"><?= t('server.user.p_ip') ?>: <?= Tools::display_ip($IP) ?></li>
                        <li class="SidebarList-item"><?= t('server.user.p_host') ?>: <?= Tools::get_host_by_ajax($IP) ?></li>
                    <? } ?>
                    <? if (check_perms('users_view_keys', $Class) || $OwnProfile) { ?>
                        <li class="SidebarList-item"><?= t('server.user.p_passkey') ?>: <a href="#" id="passkey" onclick="togglePassKey('<?= display_str($torrent_pass) ?>'); return false;"><?= t('server.user.view') ?></a></li>
                    <? } ?>
                    <? if (Applicant::user_is_applicant($UserID) && (check_perms('admin_manage_applicants') || $OwnProfile)) { ?>
                        <li class="SidebarList-item"><?= t('server.user.p_inviter') ?>: <a href="/apply.php?action=view"><?= t('server.user.view') ?></a></li>
                    <? } ?>
                    <?
                    if (!isset($SupportFor)) {
                        $DB->query('
		SELECT SupportFor
		FROM users_info
		WHERE UserID = ' . $LoggedUser['ID']);
                        list($SupportFor) = $DB->next_record();
                    }
                    if ($Override = check_perms('users_mod') || $OwnProfile || !empty($SupportFor)) {
                    ?>
                        <li class="SidebarList-item <?= (($Override === 2 || $SupportFor) ? 'paranoia_override' : '') ?>">
                            <?= t('server.user.p_clients') ?>: <?
                                                                $DB->query("
			SELECT DISTINCT useragent
			FROM xbt_files_users
			WHERE uid = $UserID");
                                                                $Clients = $DB->collect(0);
                                                                echo implode('; ', $Clients);
                                                                ?></li>
                    <?
                    }

                    if ($OwnProfile || check_perms('users_mod')) {
                        $DB->query("SELECT MAX(uhp.ChangeTime), ui.JoinDate
				FROM users_info ui
				LEFT JOIN users_history_passwords uhp ON uhp.UserID = $UserID
				WHERE ui.UserID = $UserID");
                        list($PasswordHistory, $JoinDate) = G::$DB->next_record();
                        $Age = (empty($PasswordHistory)) ? time_diff($JoinDate) : time_diff($PasswordHistory);
                        $PasswordAge = substr($Age, 0, strpos($Age, " ago"));
                    ?>
                        <li class="SidebarList-item"><?= t('server.user.p_passwordage') ?>: <?= $PasswordAge ?></li>
                    <? }
                    if ($OwnProfile || check_perms('users_override_paranoia', $Class)) { ?>
                        <li class="SidebarList-item"><?= t('server.user.p_irc') ?>: <?= empty($IRCKey) ? t('server.user.irc_no') : t('server.user.irc_yes') ?></li>
                    <? } ?>
                </ul>
            </div>

            <?
            // TODO 丧心病狂，这里又定义一遍，展示在个人页上
            $NextLevel = array();
            $NextLevel[$Classes[CONFIG['USER_CLASS']['USER']]['Level']] = array(
                'To' => $Classes[CONFIG['USER_CLASS']['MEMBER']]['Name'],
                'MinUpload' => 0,
                'MinDownload' => 80 * 1024 * 1024 * 1024,
                'MinRatio' => 0.8,
                'MinUploads' => 0,
                'MaxTime' => time_minus(3600 * 24 * 7)
            );
            $NextLevel[$Classes[CONFIG['USER_CLASS']['MEMBER']]['Level']] = array(
                'To' => $Classes[CONFIG['USER_CLASS']['POWER']]['Name'],
                'MinUpload' => 0,
                'MinDownload' => 200 * 1024 * 1024 * 1024,
                'MinRatio' => 1.2,
                'MinUploads' => 1,
                'MaxTime' => time_minus(3600 * 24 * 7 * 2)
            );
            $NextLevel[$Classes[CONFIG['USER_CLASS']['POWER']]['Level']] = array(
                'To' => $Classes[CONFIG['USER_CLASS']['ELITE']]['Name'],
                'MinUpload' => 0,
                'MinDownload' => 500 * 1024 * 1024 * 1024,
                'MinRatio' => 1.2,
                'MinUploads' => 25,
                'MaxTime' => time_minus(3600 * 24 * 7 * 4)
            );
            $NextLevel[$Classes[CONFIG['USER_CLASS']['ELITE']]['Level']] = array(
                'To' => $Classes[CONFIG['USER_CLASS']['TORRENT_MASTER']]['Name'],
                'MinUpload' => 0,
                'MinDownload' => 1 * 1024 * 1024 * 1024 * 1024,
                'MinRatio' => 1.2,
                'MinUploads' => 100,
                'MaxTime' => time_minus(3600 * 24 * 7 * 8)
            );
            $NextLevel[$Classes[CONFIG['USER_CLASS']['TORRENT_MASTER']]['Level']] = array(
                'To' => $Classes[CONFIG['USER_CLASS']['POWER_TM']]['Name'],
                'MinUpload' => 0,
                'MinDownload' => 2 * 1024 * 1024 * 1024 * 1024,
                'MinRatio' => 1.2,
                'MinUploads' => 250,
                'MaxTime' => time_minus(3600 * 24 * 7 * 12)
            );
            $NextLevel[$Classes[CONFIG['USER_CLASS']['POWER_TM']]['Level']] = array(
                'To' => $Classes[CONFIG['USER_CLASS']['ELITE_TM']]['Name'],
                'MinUpload' => 0,
                'MinDownload' => 5 * 1024 * 1024 * 1024 * 1024,
                'MinRatio' => 1.2,
                'MinUploads' => 500,
                'MaxTime' => time_minus(3600 * 24 * 7 * 16),
            );

            $NextLevel[$Classes[CONFIG['USER_CLASS']['ELITE_TM']]['Level']] = array(
                'To' => $Classes[CONFIG['USER_CLASS']['GURU']]['Name'],
                'MinUpload' => 0,
                'MinDownload' => 10 * 1024 * 1024 * 1024 * 1024,
                'MinRatio' => 1.2,
                'MinUploads' => 1000,
                'MaxTime' => time_minus(3600 * 24 * 7 * 20),
            );

            if (isset($NextLevel[$Class]) && $OwnProfile) {
            ?>
                <div class="SidebarItemUserNextClass SidebarItem Box">
                    <div class="SidebarItem-header Box-header">
                        <?= t('server.user.next_userclass') ?></div>
                    <ul class="SidebarList SidebarItem-body Box-body">
                        <li class="SidebarList-item"><?= t('server.user.next_userclass_title1') ?>: <?= $NextLevel[$Class]['To'] ?></li>
                        <li class="SidebarList-item"><?= t('server.user.next_userclass_title6') ?>: <?
                                                                                                    $p = $AllDownloaded / $NextLevel[$Class]['MinDownload'] * 100;
                                                                                                    echo Format::get_size($AllDownloaded) . ' / ' . Format::get_size($NextLevel[$Class]['MinDownload']) . " (<span class=\"" . ($p >= 100 ? "u-colorSuccess\">" : "u-colorWarning\">") . number_format($p) . "%</span>)"
                                                                                                    ?></li>
                        <li class="SidebarList-item"><?= t('server.user.next_userclass_title3') ?>: <?
                                                                                                    $p = $Uploaded / $Downloaded / $NextLevel[$Class]['MinRatio'] * 100;
                                                                                                    echo number_format($Uploaded / $Downloaded, 2) . ' / ' . $NextLevel[$Class]['MinRatio'] . " (<span class=\"" . ($p >= 100 ? "u-colorSuccess\">" : "u-colorWarning\">") . number_format($p) . "%</span>)"
                                                                                                    ?></li>
                        <li class="SidebarList-item"><?= t('server.user.next_userclass_title4') ?>: <?
                                                                                                    //$p = $JoinDate / $NextLevel[$Class]['MaxTime'] * 100;
                                                                                                    echo time_diff($JoinDate, $Levels = 2, $Span = true, $Lowercase = false, $StartTime = false, $HideAgo = true) . ' / ' . time_diff($NextLevel[$Class]['MaxTime'], $Levels = 2, $Span = true, $Lowercase = false, $StartTime = false, $HideAgo = true)
                                                                                                    ?></li>
                        <?
                        $DB->query("SELECT COUNT(ID)
				FROM torrents
				WHERE UserID = " . $UserID);
                        list($MinUploads) = $DB->next_record();
                        // test
                        $p = $MinUploads / $NextLevel[$Class]['MinUploads'] * 100;
                        echo "<li>" . t('server.user.next_userclass_title5') . ": $MinUploads / " . $NextLevel[$Class]['MinUploads'];
                        echo ($NextLevel[$Class]['MinUploads'] ? (" (<span class=\"" . ($p >= 100 ? "u-colorSuccess\">" : "u-colorWarning\">") . number_format($p) . "%</span>)") : "") . "</li>";
                        ?>
                    </ul>
                </div>
            <?
            }



            $CanViewUploads = check_perms("users_view_uploaded");
            if (check_paranoia_here('uploads+') || $CanViewUploads) {
                $DB->query("
		SELECT COUNT(ID)
		FROM torrents
		WHERE UserID = '$UserID'");
                list($Uploads) = $DB->next_record();
            } else {
                $Uploads = 0;
            }





            ?>

            <?
            include(CONFIG['SERVER_ROOT'] . '/sections/user/community_stats.php');

            DonationsView::render_donor_stats($OwnProfile, $donationInfo, $leaderBoardRank, $donorVisible, $isDonor);
            ?>
        </div>
        <div class="LayoutMainSidebar-main">
            <?
            if (
                $RatioWatchEnds != '0000-00-00 00:00:00'
                && (time() < strtotime($RatioWatchEnds))
                && ($Downloaded * $RequiredRatio) > $Uploaded
            ) {
                $RatioWatchText = t(
                    'server.user.ratio_watch_text',
                    [
                        'Values' =>
                        [
                            Format::get_size(($Downloaded * $RequiredRatio) - $Uploaded),
                            time_diff($RatioWatchEnds),
                            Format::get_size($Downloaded - $RatioWatchDownload),
                        ]
                    ]
                );
            ?>
                <div class="Box is-noBorder">
                    <div class="Box-headerTitle">
                        <div class="Box-header"><?= t('server.user.ratio_watch') ?></div>
                    </div>
                    <div class="Box-body"><?= $RatioWatchText ?></div>
                </div>
            <?
            }
            $WearOrDisplay = Badges::get_wear_badges($UserID);
            $BadgesLabels = Badges::get_badge_labels();
            $BadgesByUserID = Badges::get_badges_by_userid($UserID);
            $MaxBadges = array();
            foreach ($BadgesByUserID as $BadgeID => $BadgeInfo) {
                $Badge = Badges::get_badges_by_id($BadgeID);
                if (isset($MaxBadges[$Badge['Label']])) {
                    if ($MaxBadges[$Badge['Label']]['Level'] < $Badge['Level']) {
                        $MaxBadges[$Badge['Label']] = array('ID' => $BadgeID, 'Level' => $Badge['Level']);
                    }
                } else {
                    $MaxBadges[$Badge['Label']] = array('ID' => $BadgeID, 'Level' => $Badge['Level']);
                }
            }
            if (CONFIG['ENABLE_BADGE']) {
            ?>
                <div class="Box is-noBorder">
                    <script>
                        var i = 0;

                        function badgesDisplay() {
                            switch (i) {
                                case 0:
                                    i++
                                    $(".badge_display").show()
                                    break
                                case 1:
                                    $("#badge_display_all").hide();
                                    i++
                                    break
                                case 2:
                                    $(".badge_display").hide()
                                    i = 0
                                    break
                            }
                        }
                    </script>

                    <div class="Box-header">
                        <div class="Box-headerTitle"><a href="/badges.php"><?= t('server.user.badge_center') ?></a></div>
                        <div class="Box-headerActions">
                            <span><a href="#" onclick="badgesDisplay()"><?= t('server.common.hide') ?></a></span>
                        </div>
                    </div>
                    <div id="badge_display_head" class="Box-body badge_display">
                        <?

                        foreach ($WearOrDisplay['Profile'] as $BadgeID) {
                            $Badge = Badges::get_badges_by_id($BadgeID);
                        ?>
                            <div class="badge_container">
                                <img src="<?= $Badge['BigImage'] ?>" data-tooltip="<?= Badges::get_text($Badge['Label'], "badge_name") ?>">
                            </div>

                        <?
                        }
                        if ($OwnProfile && count($WearOrDisplay['Profile']) == 0) {
                            if (count($BadgesByUserID) == 0) {
                                echo '<span>' . t('server.badges.you_do_not_have_any_badge') . '</span>';
                            } else {
                                echo '<span>' . t('server.badges.you_do_not_display_any_badge') . '</span>';
                            }
                        }
                        ?>
                    </div>
                    <div id="badge_display_all" class="Box-body badge_display" style="display: none;">
                        <?
                        if (check_paranoia_here('badgedisplay') || check_perms('users_override_paranoia')) {
                            foreach ($MaxBadges as $Label => $BadgeInfo) {
                                if (in_array($BadgeInfo['ID'], $WearOrDisplay['Profile'])) continue;
                                $Badge = Badges::get_badges_by_id($BadgeInfo['ID']);
                        ?>
                                <div class="badge_container">
                                    <img src="<?= $Badge['BigImage'] ?>" data-tooltip="<?= Badges::get_text($Label, "badge_name") ?>">
                                </div>

                        <?
                            }
                        }
                        ?>
                    </div>
                </div>
            <?
            }
            ?>

            <div class="Post">
                <div class="Post-header">
                    <div class="Post-headerLeft">
                        <div class="Post-headerTitle"><?= !empty($InfoTitle) ? $InfoTitle : t('server.user.infotitle'); ?>
                        </div>
                    </div>
                    <div class="Post-headerActions">
                        <a href="#" onclick="globalapp.toggleAny(event, '#profilediv');return false;">
                            <span class="u-toggleAny-show u-hidden"><?= t('server.common.show') ?></span>
                            <span class="u-toggleAny-hide"><?= t('server.common.hide') ?></span>
                        </a>
                    </div>
                </div>
                <div class="Post-body HtmlText PostArticle profileinfo" id="profilediv">
                    <?
                    if (!$Info) {
                    ?>
                        <?= t('server.user.no_infotitle') ?>
                    <?
                    } else {
                        echo Text::full_format($Info);
                    }
                    ?>
                </div>
            </div>
            <?
            DonationsView::render_profile_rewards($EnabledRewards, $ProfileRewards);

            if (check_paranoia_here('snatched')) {
                $RecentSnatches = $Cache->get_value("recent_snatches_$UserID");
                if ($RecentSnatches === false) {
                    $DB->query("
			SELECT
                t.ID AS TorrentID,
				g.ID,
				g.Name,
				g.WikiImage,
                g.SubName,
                g.Year
			FROM xbt_snatched AS s
				INNER JOIN torrents AS t ON t.ID = s.fid
				INNER JOIN torrents_group AS g ON t.GroupID = g.ID
			WHERE s.uid = '$UserID'
				AND t.UserID != '$UserID'
				AND g.CategoryID = '1'
				AND g.WikiImage != ''
			GROUP BY g.ID
			ORDER BY s.tstamp DESC
			LIMIT 5");
                    $RecentSnatches = $DB->to_array();
                    $Cache->cache_value("recent_snatches_$UserID", $RecentSnatches, 0); //inf cache
                }
                if (!empty($RecentSnatches)) {
            ?>
                    <div class="Box is-noBorder" id="recent_snatches">
                        <div class="Box-header">
                            <div class="Box-headerTitle">
                                <?= t('server.user.last_torrents') ?>
                            </div>
                        </div>
                        <div class="Box-body">
                            <?
                            $tableRender = new TorrentGroupCoverTableView($RecentSnatches);
                            $tableRender->render(['UseTorrentID' => true]);
                            ?>
                        </div>
                    </div>
                <?
                }
            }

            if (check_paranoia_here('uploads')) {
                $RecentUploads = $Cache->get_value("recent_uploads_$UserID");
                if ($RecentUploads === false) {
                    $DB->query("
			SELECT
                t.ID AS TorrentID,
				g.ID,
				g.Name,
                g.SubName,
                g.Year,
				g.WikiImage
			FROM torrents_group AS g
				INNER JOIN torrents AS t ON t.GroupID = g.ID
			WHERE t.UserID = '$UserID'
				AND g.CategoryID = '1'
				AND g.WikiImage != ''
			GROUP BY g.ID
			ORDER BY t.Time DESC
			LIMIT 5");
                    $RecentUploads = $DB->to_array();
                    $Cache->cache_value("recent_uploads_$UserID", $RecentUploads, 0); //inf cache
                }
                if (!empty($RecentUploads)) {
                ?>
                    <div class="Box is-noBorder" id="recent_uploads">
                        <div class="Box-header">
                            <div class="Box-headerTitle">
                                <?= t('server.user.last_uploads') ?>
                            </div>
                        </div>
                        <div class="Box-body">
                            <?
                            $tableRender = new TorrentGroupCoverTableView($RecentUploads);
                            $tableRender->render(['UseTorrentID' => true]);
                            ?>
                        </div>
                    </div>
                <?
                }
            }
            $DB->query("
	SELECT ID, Name
	FROM collages
	WHERE UserID = '$UserID'
		AND CategoryID = '$PersonalCollageCategoryCat'
		AND Deleted = '0'
	ORDER BY Featured DESC,
		Name ASC");
            $Collages = $DB->to_array(false, MYSQLI_NUM, false);
            $FirstCol = true;
            foreach ($Collages as $CollageInfo) {
                list($CollageID, $CName) = $CollageInfo;
                $DB->query("
		SELECT ct.GroupID
		FROM collages_torrents AS ct
		WHERE ct.CollageID = '$CollageID'
		ORDER BY ct.Sort
		LIMIT 5");
                $GroupIDs = $DB->collect('GroupID');
                $Groups = Torrents::get_groups($GroupIDs, true, false, false);
                ?>
                <div class="Box is-noBorder" id="collage<?= $CollageID ?>_box">
                    <div class="Box-header">
                        <div class="Box-headerTitle">
                            <?= $CName ?>
                        </div>
                        <div class="Box-headerActions">
                            <span>
                                <a href="collages.php?id=<?= $CollageID ?>">See full</a>
                            </span>
                            -
                            <span>
                                <a href="#" onclick="globalapp.toggleAny(event, '#collage<?= $CollageID ?>_box .Box-body');return false;">
                                    <span class="u-toggleAny-show <?= $FirstCol ? 'u-hidden' : '' ?>"><?= t('server.common.show') ?></span>
                                    <span class="u-toggleAny-hide <?= $FirstCol ? '' : 'u-hidden' ?>"><?= t('server.common.hide') ?></span>
                                </a>
                            </span>
                        </div>
                    </div>
                    <div class="Box-body <?= $FirstCol ? '' : 'u-hidden' ?>">
                        <?
                        $tableRender = new TorrentGroupCoverTableView($Groups);
                        $tableRender->render();
                        ?>
                    </div>
                </div>
                <?
                $FirstCol = false;
            }
            if (empty($LoggedUser['DisableRequests']) && check_paranoia_here('requestsvoted_list')) {
                $SphQL = new SphinxqlQuery();
                $SphQLResult = $SphQL->select('id, votes, bounty')
                    ->from('requests, requests_delta')
                    ->where('userid', $UserID)
                    ->where('torrentid', 0)
                    ->order_by('votes', 'desc')
                    ->order_by('bounty', 'desc')
                    ->limit(0, 100, 100) // Limit to 100 requests
                    ->query();
                if ($SphQLResult->has_results()) {
                    $SphRequests = $SphQLResult->to_array('id', MYSQLI_ASSOC);
                ?>
                    <div class="Box is-noBorder" id="requests_box">
                        <div class="Box-header">
                            <div class="Box-headerTitle">
                                <?= t('server.common.requests') ?>
                            </div>
                            <div class="Box-headerActions">
                                <a href="#" onclick="globalapp.toggleAny(event, '#requests');return false;">
                                    <span class="u-toggleAny-show"><?= t('server.common.show') ?></span>
                                    <span class="u-toggleAny-hide u-hidden"><?= t('server.common.hide') ?></span>
                                </a>
                            </div>
                        </div>
                        <div class="Box-body TableCotainer u-hidden" id="requests">
                            <table class="Table TableRequest" cellpadding="6" cellspacing="1" border="0" width="100%">
                                <tr class="Table-rowHeader">
                                    <td class="Table-cell">
                                        <?= t('server.user.name') ?>
                                    </td>
                                    <td class="Table-cell TableRequest-cellValue">
                                        <?= t('server.user.vote') ?>
                                    </td>
                                    <td class="Table-cell TableRequest-cellValue">
                                        <?= t('server.user.bounty') ?>
                                    </td>
                                    <td class="Table-cell TableRequest-cellValue">
                                        <?= t('server.user.add_time') ?>
                                    </td>
                                </tr>
                                <?
                                $Requests = Requests::get_requests(array_keys($SphRequests));
                                foreach ($SphRequests as $RequestID => $SphRequest) {
                                    $Request = $Requests[$RequestID];

                                    $RequestVotes = Requests::get_votes_array($Request['ID']);
                                    $RequestID = $Request['ID'];
                                    $RequestName = Torrents::group_name($Request, false);
                                    $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\">$RequestName</a>";

                                ?>
                                    <tr class="Table-row">
                                        <td class="TableRequest-cellName Table-cell">
                                            <?= $FullName ?>
                                            <div class="torrent_info">
                                                <?
                                                ?>
                                                <?= str_replace('|', ', ', $Request['CodecList']) . ' / ' . str_replace('|', ', ', $Request['SourceList']) . ' / ' . str_replace('|', ', ', $Request['ResolutionList']) . ' / ' . str_replace('|', ', ', $Request['ContainerList']) ?>
                                            </div>
                                        </td>
                                        <td class="TableRequest-cellVotes Table-cell TableRequest-cellValue">
                                            <span id="vote_count_<?= $Request['ID'] ?>"><?= count($RequestVotes['Voters']) ?></span>
                                            <? if (check_perms('site_vote')) { ?>
                                                &nbsp;&nbsp; <a href="javascript:globalapp.requestVote(0, <?= $Request['ID'] ?>)" class="brackets">+</a>
                                            <?          } ?>
                                        </td>
                                        <td class="TableRequest-cellBounty Table-cell TableRequest-cellValue">
                                            <?= Format::get_size($RequestVotes['TotalBounty']) ?>
                                        </td>
                                        <td class="TableRequest-cellCreatedAt TableRequest-cellValue Table-cell">
                                            <?= time_diff($Request['TimeAdded'], 1) ?>
                                        </td>
                                    </tr>
                                <?  } ?>
                            </table>
                        </div>
                    </div>
                <?
                }
            }


            if ((check_perms('users_view_invites')) && $Invited > 0) {
                include(CONFIG['SERVER_ROOT'] . '/classes/invite_tree.class.php');
                $Tree = new INVITE_TREE($UserID, array('visible' => false));
                ?>
                <div class="Box is-noBorder" id="invitetree_box">
                    <div class="Box-header">
                        <div class="Box-headerTitle">
                            <?= t('server.user.invite_tree') ?>
                        </div>
                        <div class="Box-headerActions">
                            <a href="#" onclick="globalapp.toggleAny(event, '#invitetree');return false;">
                                <span class="u-toggleAny-show"><?= t('server.common.show') ?></span>
                                <span class="u-toggleAny-hide u-hidden"><?= t('server.common.hide') ?></span>
                            </a>
                        </div>
                    </div>
                    <div id="invitetree" class="u-hidden Box-body">
                        <? $Tree->make_tree(); ?>
                    </div>
                </div>
                <?
            }

            // Linked accounts

            if (check_perms('users_mod')) {
                DonationsView::render_donation_history($donation->history($UserID));
            }
            $IsFLS = isset($LoggedUser['ExtraClasses'][CONFIG['USER_CLASS']['FLS_TEAM']]);
            if (check_perms('users_mod', $Class) || $IsFLS) {
                $UserLevel = $LoggedUser['EffectiveClass'];
                $DB->query("
		SELECT
			SQL_CALC_FOUND_ROWS
			spc.ID,
			spc.Subject,
			spc.Status,
			spc.Level,
			spc.AssignedToUser,
			spc.Date,
			COUNT(spm.ID) AS Resplies,
			spc.ResolverID
		FROM staff_pm_conversations AS spc
		JOIN staff_pm_messages spm ON spm.ConvID = spc.ID
		WHERE spc.UserID = $UserID
			AND (spc.Level <= $UserLevel OR spc.AssignedToUser = '" . $LoggedUser['ID'] . "')
		GROUP BY spc.ID
		ORDER BY spc.Date DESC");
                if ($DB->has_results()) {
                    $StaffPMs = $DB->to_array();
                ?>
                    <div class="Box is-noBorder" id="staffpms_box">
                        <div class="Box-header">
                            <div class="Box-headerTitle">
                                <?= t('server.user.staff_pm') ?>
                            </div>
                            <div class="Box-headerActions">
                                <a href="#" onclick="globalapp.toggleAny(event, '#staffpms');return false;">
                                    <span class="u-toggleAny-show"><?= t('server.common.show') ?></span>
                                    <span class="u-toggleAny-hide u-hidden"><?= t('server.common.hide') ?></span>
                                </a>
                            </div>
                        </div>
                        <div class="Box-body u-hidden" id="staffpms">
                            <table class="Table TableUserInbox">
                                <tr class="Table-rowHeader">
                                    <td class="Table-cell"><?= t('server.user.subject') ?></td>
                                    <td class="Table-cell"><?= t('server.user.date') ?></td>
                                    <td class="Table-cell"><?= t('server.user.assigned_to') ?></td>
                                    <td class="Table-cell"><?= t('server.user.replies') ?></td>
                                    <td class="Table-cell"><?= t('server.user.resolved_by') ?></td>
                                </tr>
                                <?
                                foreach ($StaffPMs as $StaffPM) {
                                    list($ID, $Subject, $Status, $Level, $AssignedToUser, $Date, $Replies, $ResolverID) = $StaffPM;
                                    // Get assigned
                                    if ($AssignedToUser == '') {
                                        // Assigned to class
                                        $Assigned = ($Level == 0) ? 'First Line Support' : $ClassLevels[$Level]['Name'];
                                        // No + on Sysops
                                        if ($Assigned != 'Sysop') {
                                            $Assigned .= '+';
                                        }
                                    } else {
                                        // Assigned to user
                                        $Assigned = Users::format_username($UserID, true, true, true, true);
                                    }

                                    if ($ResolverID) {
                                        $Resolver = Users::format_username($ResolverID, true, true, true, true);
                                    } else {
                                        $Resolver = '(unresolved)';
                                    }

                                ?>
                                    <tr class="Table-row">
                                        <td class="Table-cell"><a href="staffpm.php?action=viewconv&amp;id=<?= $ID ?>"><?= display_str($Subject) ?></a></td>
                                        <td class="Table-cell"><?= time_diff($Date, 2, true) ?></td>
                                        <td class="Table-cell"><?= $Assigned ?></td>
                                        <td class="Table-cell"><?= $Replies - 1 ?></td>
                                        <td class="Table-cell"><?= $Resolver ?></td>
                                    </tr>
                                <? } ?>
                            </table>
                        </div>
                    </div>
                <?
                }
            }

            // Displays a table of forum warnings viewable only to Forum Moderators
            if ($LoggedUser['Class'] > 650 && check_perms('users_warn', $Class)) {
                $DB->query("
		SELECT Comment
		FROM users_warnings_forums
		WHERE UserID = '$UserID'");
                list($ForumWarnings) = $DB->next_record();
                if ($DB->has_results()) {
                ?>
                    <div class="Post">
                        <div class="Post-header">
                            <div class="Post-headerTitle">
                                <?= t('server.user.forum_warnings') ?></div>
                        </div>
                        <div class="Post-body">
                            <div id="forumwarningslinks" class="HtmlText AdminComment" style="width: 98%;">
                                <?= Text::full_format($ForumWarnings) ?>
                            </div>
                        </div>
                    </div>
                <?
                }
            }
            if (check_perms('users_mod')) {
                include(CONFIG['SERVER_ROOT'] . '/sections/user/linkedfunctions.php');
                user_dupes_table($UserID);
            }
            if (check_perms('users_mod', $Class)) {
                ?>
                <div class="Box is-noBorder" id="staff_notes_box">
                    <div class="Box-header">
                        <div class="Box-headerTitle">
                            <?= t('server.user.staff_note') ?>
                        </div>
                    </div>
                    <div id="staffnotes" class="Box-body">
                        <div id="admincommentlinks" class="HtmlText AdminComment" style="width: 98%;">
                            <?= Text::full_format($AdminComment) ?>
                        </div>
                    </div>
                </div>
            <?
            }

            ?>
            <!-- for the "jump to staff tools" button -->

            <?

            if (check_perms('users_mod', $Class)) { ?>
                <form id="staff_tools" class="Form manage_form" name="user" id="form" action="user.php" method="post">
                    <input type="hidden" name="comment_hash" value="<?= $CommentHash ?>" />
                    <input type="hidden" name="action" value="moderate" />
                    <input type="hidden" name="userid" value="<?= $UserID ?>" />
                    <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                    <div class="Form-rowList" variant="header">
                        <table>
                            <tr class="Form-rowHeader">
                                <td class="Form-title" colspan="2">
                                    <?= t('server.user.staff_tools') ?>
                                </td>
                            </tr>
                            <tr class="Form-rowSubHeader">
                                <td class="Form-title" colspan="2">
                                    <?= t('server.user.info') ?>
                                </td>
                            </tr>
                            <? if (check_perms('users_edit_usernames', $Class)) { ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.account') ?></td>
                                    <td class="Form-inputs"><input class="Input" type="text" size="20" name="Username" value="<?= display_str($Username) ?>" /></td>
                                </tr>
                            <?
                            }
                            if (check_perms('users_edit_titles')) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.customtitle') ?></td>
                                    <td class="Form-inputs"><input class="Input" type="text" name="Title" value="<?= display_str($CustomTitle) ?>" /></td>
                                </tr>
                            <?
                            }

                            if (check_perms('users_promote_below', $Class) || check_perms('users_promote_to', $Class - 1)) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.promote_class') ?></td>
                                    <td class="Form-inputs">
                                        <select class="Input" name="Class">
                                            <?
                                            foreach ($ClassLevels as $CurClass) {
                                                if ($CurClass['Secondary']) {
                                                    continue;
                                                } elseif ($LoggedUser['ID'] != $UserID && !check_perms('users_promote_to', $Class - 1) && $CurClass['Level'] == $LoggedUser['EffectiveClass']) {
                                                    break;
                                                } elseif ($CurClass['Level'] > $LoggedUser['EffectiveClass']) {
                                                    break;
                                                }
                                                if ($Class === $CurClass['Level']) {
                                                    $Selected = ' selected="selected"';
                                                } else {
                                                    $Selected = '';
                                                }
                                            ?>
                                                <option class="Select-option" value="<?= $CurClass['ID'] ?>" <?= $Selected ?>><?= $CurClass['Name'] . ' (' . $CurClass['Level'] . ')' ?></option>
                                            <?      } ?>
                                        </select>
                                    </td>
                                </tr>
                            <?
                            }

                            if (check_perms('users_give_donor')) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.donor') ?></td>
                                    <td class="Form-inputs"><input class="Input" type="checkbox" name="Donor" <? if ($Donor == 1) { ?> checked="checked" <? } ?> /></td>
                                </tr>
                            <?
                            }
                            if (check_perms('users_promote_below') || check_perms('users_promote_to')) { ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.se_class') ?></td>
                                    <td class="Form-inputs">
                                        <?
                                        $DB->query("
			SELECT p.ID, p.Name, l.UserID
			FROM permissions AS p
				LEFT JOIN users_levels AS l ON l.PermissionID = p.ID AND l.UserID = '$UserID'
			WHERE p.Secondary = 1
			ORDER BY p.Name");
                                        $i = 0;
                                        while (list($PermID, $PermName, $IsSet) = $DB->next_record()) {
                                            $i++;
                                        ?>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" id="perm_<?= $PermID ?>" name="secondary_classes[]" value="<?= $PermID ?>" <? if ($IsSet) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="perm_<?= $PermID ?>"><?= $PermName ?></label>
                                            </div>
                                        <? if ($i % 3 == 0) {
                                                echo "\t\t\t\t<br />\n";
                                            }
                                        } ?>
                                    </td>
                                </tr>
                            <?  }
                            if (check_perms('users_make_invisible')) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.view_list') ?></td>
                                    <td class="Form-inputs">
                                        <input class="Input" type="checkbox" name="Visible" <? if ($Visible == 1) { ?> checked="checked" <? } ?> />
                                    </td>
                                </tr>
                            <?
                            }

                            if (check_perms('users_edit_ratio', $Class) || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID'])) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="<?= t('server.user.uploaded_title') ?>"><?= t('server.user.uploaded') ?></td>
                                    <td class="Form-inputs">
                                        <input type="hidden" name="OldUploaded" value="<?= $Uploaded ?>" />
                                        <input class="Input" type="text" size="20" name="Uploaded" value="<?= $Uploaded ?>" />
                                    </td>
                                </tr>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="<?= t('server.user.downloaded_title') ?>"><?= t('server.user.downloaded') ?></td>
                                    <td class="Form-inputs">
                                        <input type="hidden" name="OldDownloaded" value="<?= $Downloaded ?>" />
                                        <input class="Input" type="text" size="20" name="Downloaded" value="<?= $Downloaded ?>" />
                                    </td>
                                </tr>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="<?= t('server.user.bonus_points_title') ?>"><?= t('server.user.bonus_points') ?></td>
                                    <td class="Form-inputs">
                                        <input type="hidden" name="OldBonusPoints" value="<?= $BonusPoints ?>" />
                                        <input class="Input" type="text" size="20" name="BonusPoints" value="<?= $BonusPoints ?>" />
                                    </td>
                                </tr>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="<?= t('server.user.merge_from_title') ?>"><?= t('server.user.merge_from') ?></td>
                                    <td class="Form-inputs">
                                        <input class="Input" type="text" size="40" name="MergeStatsFrom" />
                                    </td>
                                </tr>
                            <?
                            }

                            if (check_perms('users_edit_invites')) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="Number of invites"><?= t('server.user.invite') ?></td>
                                    <td class="Form-inputs"><input class="Input" type="text" size="5" name="Invites" value="<?= $Invites ?>" /></td>
                                </tr>
                            <?
                            }

                            if (check_perms('admin_manage_user_fls')) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="Number of FL tokens"><?= t('server.user.token') ?></td>
                                    <td class="Form-inputs"><input class="Input" type="text" size="5" name="FLTokens" value="<?= $FLTokens ?>" /></td>
                                </tr>
                            <?
                            }

                            if (check_perms('admin_manage_fls') || (check_perms('users_mod') && $OwnProfile)) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="<?= t('server.user.staff_mark_title') ?>"><?= t('server.user.staff_mark') ?></td>
                                    <td class="Form-inputs"><input class="Input" type="text" name="SupportFor" value="<?= display_str($SupportFor) ?>" /></td>
                                </tr>
                            <?
                            }

                            if (check_perms('users_edit_reset_keys')) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.reset') ?></td>
                                    <td class="Form-inputs" id="reset_td">
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="ResetRatioWatch" id="ResetRatioWatch" />
                                            <label class="Checkbox-label" for="ResetRatioWatch"><?= t('server.user.ratio_watch') ?></label>
                                        </div>
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="ResetPasskey" id="ResetPasskey" />
                                            <label class="Checkbox-label" for="ResetPasskey"><?= t('server.user.passkey') ?></label>
                                        </div>
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="ResetAuthkey" id="ResetAuthkey" />
                                            <label class="Checkbox-label" for="ResetAuthkey"><?= t('server.user.authkey') ?></label>
                                        </div>
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="ResetIPHistory" id="ResetIPHistory" />
                                            <label class="Checkbox-label" for="ResetIPHistory"><?= t('server.user.ip_history') ?></label>
                                        </div>
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="ResetEmailHistory" id="ResetEmailHistory" />
                                            <label class="Checkbox-label" for="ResetEmailHistory"><?= t('server.user.email_history') ?></label>
                                        </div>
                                        <br />
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="ResetSnatchList" id="ResetSnatchList" />
                                            <label class="Checkbox-label" for="ResetSnatchList"><?= t('server.user.snatch_list') ?></label>
                                        </div>
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="ResetDownloadList" id="ResetDownloadList" />
                                            <label class="Checkbox-label" for="ResetDownloadList"><?= t('server.user.download_list') ?></label>
                                        </div>
                                    </td>
                                </tr>
                            <?
                            }

                            if (check_perms('users_edit_password')) {
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.new_password') ?></td>
                                    <td class="Form-inputs">
                                        <input class="Input" type="text" size="30" id="change_password" name="ChangePassword" />
                                        <button class="Button" type="button" id="random_password"><?= t('server.user.generate') ?></button>
                                    </td>
                                </tr>

                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.2fa') ?></td>
                                    <td class="Form-inputs">
                                        <? if ($FA_Key) { ?>
                                            <a href="user.php?action=2fa&page=user&do=disable&userid=<?= $UserID ?>"><?= t('server.user.close') ?></a>
                                        <? } else { ?>
                                            <?= t('server.user.closed') ?>
                                        <? } ?>
                                    </td>
                                </tr>
                            <? } ?>

                            <? if (check_perms('users_warn')) { ?>
                                <tr class="Form-rowSubHeader">
                                    <td class="Form-title" colspan="2">
                                        <?= t('server.user.warn') ?>
                                    </td>
                                </tr>
                                <tr class="Form-row">
                                    <td class="Form-label">
                                        <?= t('server.user.warned') ?>
                                    </td>
                                    <td class="Form-inputs">
                                        <input type="checkbox" name="Warned" <? if ($Warned != '0000-00-00 00:00:00') { ?> checked="checked" <? } ?> />
                                    </td>
                                </tr>
                                <? if ($Warned == '0000-00-00 00:00:00') { /* user is not warned */ ?>
                                    <tr class="Form-row">
                                        <td class="Form-label">
                                            <?= t('server.user.warn_time') ?>
                                        </td>
                                        <td class="Form-inputs">
                                            <select class="Input" name="WarnLength">
                                                <option class="Select-option" value="">---</option>
                                                <option class="Select-option" value="1"><?= t('server.user.1_week') ?></option>
                                                <option class="Select-option" value="2"><?= t('server.user.2_week') ?></option>
                                                <option class="Select-option" value="4"><?= t('server.user.4_week') ?></option>
                                                <option class="Select-option" value="8"><?= t('server.user.8_week') ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                <? } else { /* user is warned */ ?>
                                    <tr class="Form-row">
                                        <td class="Form-label">
                                            <?= t('server.user.warn_time') ?>
                                        </td>
                                        <td class="Form-inputs">
                                            <select class="Input" name="ExtendWarning" onchange="ToggleWarningAdjust(this);">
                                                <option class="Select-option">---</option>
                                                <option class="Select-option" value="1"><?= t('server.user.1_week') ?></option>
                                                <option class="Select-option" value="2"><?= t('server.user.2_week') ?></option>
                                                <option class="Select-option" value="4"><?= t('server.user.4_week') ?></option>
                                                <option class="Select-option" value="8"><?= t('server.user.8_week') ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="Form-row" id="ReduceWarningTR">
                                        <td class="Form-label">
                                            <?= t('server.user.free_time') ?>
                                        </td>
                                        <td class="Form-inputs">
                                            <select class="Input" name="ReduceWarning">
                                                <option class="Select-option">---</option>
                                                <option class="Select-option" value="1"><?= t('server.user.1_week') ?></option>
                                                <option class="Select-option" value="2"><?= t('server.user.2_week') ?></option>
                                                <option class="Select-option" value="4"><?= t('server.user.4_week') ?></option>
                                                <option class="Select-option" value="8"><?= t('server.user.8_week') ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                <? } ?>
                                <tr class="Form-row">
                                    <td class="Form-label" data-tooltip="<?= t('server.user.warn_reason_title') ?>">
                                        <?= t('server.user.warn_reason') ?>
                                    </td>
                                    <td class="Form-inputs">
                                        <input class="Input" type="text" name="WarnReason" />
                                    </td>
                                </tr>
                            <?  } ?>
                            <? if (check_perms('users_disable_any')) { ?>
                                <tr class="Form-rowSubHeader">
                                    <td class="Form-title" colspan="2">
                                        <?= t('server.user.disable_account') ?>
                                    </td>
                                </tr>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.account_disable') ?></td>
                                    <td class="Form-inputs">
                                        <input type="checkbox" name="LockAccount" id="LockAccount" <? if ($LockedAccount) { ?> checked="checked" <? } ?> />
                                    </td>
                                </tr>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.reason') ?></td>
                                    <td class="Form-inputs">
                                        <select class="Input" name="LockReason">
                                            <option class="Select-option" value="---">---</option>
                                            <option class="Select-option" value="<?= STAFF_LOCKED ?>" <? if ($LockedAccount == STAFF_LOCKED) { ?> selected <? } ?>><?= t('server.user.admin_account') ?></option>
                                        </select>
                                    </td>
                                </tr>
                            <?  }  ?>
                            <tr class="Form-rowSubHeader">
                                <td class="Form-title" colspan="2">
                                    <?= t('server.user.user_po') ?>
                                </td>
                            </tr>
                            <? if (check_perms('users_disable_posts') || check_perms('users_disable_any')) {
                                $DB->query("
			SELECT DISTINCT Email, IP
			FROM users_history_emails
			WHERE UserID = $UserID
			ORDER BY Time ASC");
                                $Emails = $DB->to_array();
                            ?>
                                <tr class="Form-row">
                                    <td class="Form-label"><?= t('server.user.user_disable') ?></td>
                                    <td class="Form-inputs">
                                        <div class="Checkbox">
                                            <input class="Input" type="checkbox" name="DisablePosting" id="DisablePosting" <? if ($DisablePosting == 1) { ?> checked="checked" <? } ?> />
                                            <label class="Checkbox-label" for="DisablePosting"><?= t('server.user.posting') ?></label>
                                        </div>
                                        <? if (check_perms('users_disable_any')) { ?>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableAvatar" id="DisableAvatar" <? if ($DisableAvatar == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableAvatar"><?= t('server.user.avatar') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableForums" id="DisableForums" <? if ($DisableForums == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableForums"><?= t('server.user.forums') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableIRC" id="DisableIRC" <? if ($DisableIRC == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableIRC"><?= t('server.user.irc') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisablePM" id="DisablePM" <? if ($DisablePM == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisablePM"><?= t('server.user.pm') ?></label>
                                            </div>
                                            <br />
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableLeech" id="DisableLeech" <? if ($DisableLeech == 0) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableLeech"><?= t('server.user.leech') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableRequests" id="DisableRequests" <? if ($DisableRequests == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableRequests"><?= t('server.common.requests') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableUpload" id="DisableUpload" <? if ($DisableUpload == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableUpload"><?= t('server.user.torrent_upload') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisablePoints" id="DisablePoints" <? if ($DisablePoints == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisablePoints"><?= t('server.user.bonus_points') ?></label>
                                            </div>
                                            <br />
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableTagging" id="DisableTagging" <? if ($DisableTagging == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableTagging" data-tooltip="<?= t('server.user.tagging_title') ?>"><?= t('server.user.tagging') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableWiki" id="DisableWiki" <? if ($DisableWiki == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableWiki"><?= t('server.user.wiki') ?></label>
                                            </div>
                                            <br />
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableInvites" id="DisableInvites" <? if ($DisableInvites == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableInvites"><?= t('server.user.invites') ?></label>
                                            </div>
                                            <br />
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableCheckAll" id="DisableCheckAll" <? if ($DisableCheckAll == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableCheckAll"><?= t('server.user.check_all_torrents') ?></label>
                                            </div>
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="DisableCheckSelf" id="DisableCheckSelf" <? if ($DisableCheckSelf == 1) { ?> checked="checked" <? } ?> />
                                                <label class="Checkbox-label" for="DisableCheckSelf"><?= t('server.user.check_his_her_torrents') ?></label>
                                            </div>
                                    </td>
                                </tr>
                                <? if ($Emails) { ?>
                                    <tr class="Form-row">
                                        <td class="Form-label"><?= t('server.user.hacked') ?></td>
                                        <td class="Form-inputs">
                                            <div class="Checkbox">
                                                <input class="Input" type="checkbox" name="SendHackedMail" id="SendHackedMail" />
                                                <label class="Checkbox-label" for="SendHackedMail">
                                                    <?= t('server.user.send_hacked_account_email_to') ?>
                                                </label>
                                            </div>
                                            <select class="Input" name="HackedEmail">
                                                <?
                                                foreach ($Emails as $Email) {
                                                    list($Address, $IP) = $Email;
                                                ?>
                                                    <option class="Select-option" value="<?= display_str($Address) ?>">
                                                        <?= display_str($Address) ?> - <?= display_str($IP) ?>
                                                    </option>
                                                <? } ?>
                                            </select>
                                        </td>
                                    </tr>
                                <? } ?>
                            <? } ?>
                        <? } ?>
                        <? if (check_perms('users_disable_any')) { ?>
                            <tr class="Form-row">
                                <td class="Form-label"><?= t('server.user.account') ?></td>
                                <td class="Form-inputs">
                                    <select class="Input" name="UserStatus">
                                        <option class="Select-option" value="0" <? if ($Enabled == '0') { ?> selected="selected" <? } ?>><?= t('server.user.unconfirmed') ?></option>
                                        <option class="Select-option" value="1" <? if ($Enabled == '1') { ?> selected="selected" <? } ?>><?= t('server.user.enabled') ?></option>
                                        <option class="Select-option" value="2" <? if ($Enabled == '2') { ?> selected="selected" <? } ?>><?= t('server.user.disabled') ?></option>
                                        <? if (check_perms('users_delete_users')) { ?>
                                            <optgroup class="Select-group" label="-- WARNING --">
                                                <option class="Select-option" value="delete"><?= t('server.user.delete_account') ?></option>
                                            </optgroup>
                                        <?      } ?>
                                    </select>
                                </td>
                            </tr>
                            <tr class="Form-row">
                                <td class="Form-label"><?= t('server.user.user_reason') ?></td>
                                <td class="Form-inputs">
                                    <input class="Input" type="text" name="UserReason" />
                                </td>
                            </tr>
                            <tr class="Form-row">
                                <td class="Form-label" data-tooltip="<?= t('server.user.restricted_forums_title') ?>"><?= t('server.user.restricted_forums') ?></td>
                                <td class="Form-inputs">
                                    <input class="Input" type="text" name="RestrictedForums" value="<?= display_str($RestrictedForums) ?>" />
                                </td>
                            </tr>
                            <tr class="Form-row">
                                <td class="Form-label" data-tooltip="<?= t('server.user.permitted_forums_title') ?>"><?= t('server.user.permitted_forums') ?></td>
                                <td class="Form-inputs">
                                    <input class="Input" type="text" name="PermittedForums" value="<?= display_str($PermittedForums) ?>" />
                                </td>
                            </tr>

                        <?  } ?>
                        <? if (check_perms('users_logout')) { ?>
                            <tr class="Form-rowSubHeader">
                                <td class="Form-title" colspan="2">
                                    <?= t('server.user.session') ?>
                                </td>
                            </tr>
                            <tr class="Form-row">
                                <td class="Form-label"><?= t('server.user.reset_session') ?></td>
                                <td class="Form-inputs"><input type="checkbox" name="ResetSession" id="ResetSession" /></td>
                            </tr>
                            <tr class="Form-row">
                                <td class="Form-label"><?= t('server.user.logout') ?></td>
                                <td class="Form-inputs"><input type="checkbox" name="LogOut" id="LogOut" /></td>
                            </tr>
                        <?
                        }
                        if (check_perms('users_mod')) {
                            DonationsView::render_mod_donations($donationInfo['Rank'], $donationInfo['TotRank']);
                        }
                        ?>
                        <tr class="Form-rowSubHeader">
                            <td class="Form-title" colspan="2"><?= t('server.user.submit') ?></td>
                        </tr>
                        <tr class="Form-row">
                            <td class="Form-label" data-tooltip="<?= t('server.user.reason_title') ?>"><?= t('server.user.reason') ?>:</td>
                            <td class="Form-inputs">
                                <textarea class="Input wide_input_text" rows="1" cols="35" name="Reason" id="Reason" onkeyup="resize('Reason');"></textarea>
                            </td>
                        </tr>
                        <tr class="Form-row">
                            <td class="Form-label"><?= t('server.user.paste_user_stats') ?>:</td>
                            <td class="Form-inputs">
                                <button class="Button" type="button" id="paster"><?= t('server.user.paste') ?></button>
                            </td>
                        </tr>
                        <tr class="Form-row">
                            <td colspan="2">
                                <button class="Button" type="submit" value="Save changes"><?= t('client.common.save') ?></button>
                            </td>
                        </tr>
                        </table>
                    </div>
                </form>
            <?
            }
            ?>
        </div>
    </div>
</div>
<? View::show_footer(); ?>