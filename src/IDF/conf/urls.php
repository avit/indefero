<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

$ctl = array();
$base = Pluf::f('idf_base');

$ctl[] = array('regex' => '#^/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'index');

$ctl[] = array('regex' => '#^/login/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'login',
               'name' => 'login_view');

$ctl[] = array('regex' => '#^/preferences/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'myAccount');

$ctl[] = array('regex' => '#^/dashboard/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'dashboard',
               'name' => 'idf_dashboard');

$ctl[] = array('regex' => '#^/dashboard/submitted/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'dashboard',
               'params' => false,
               'name' => 'idf_dashboard_submit');

$ctl[] = array('regex' => '#^/u/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'view');

$ctl[] = array('regex' => '#^/logout/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'logout');

$ctl[] = array('regex' => '#^/help/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'faq');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'home');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/timeline/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timeline');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/feed/timeline/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timelineFeed',
               'name' => 'idf_project_timeline_feed');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/feed/timeline/token/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timelineFeed',
               'name' => 'idf_project_timeline_feed_auth');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/search/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'search');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/(\d+)/star/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'star');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/status/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'listStatus');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/label/(\d+)/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/my/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'myIssues');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/attachment/(\d+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'getAttachment');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/view/attachment/(\d+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'viewAttachment');

// ---------- SCM ----------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/help/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'help');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/tree/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'treeBase');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/tree/([^/]+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'tree');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/changes/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'changeLog');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/commit/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'commit');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/ddiff/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'downloadDiff');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/download/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'download');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/file/([^/]+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'getFile');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/treerev/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source_Svn',
               'method' => 'treeRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/changesrev/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source_Svn',
               'method' => 'changelogRev');

// ---------- WIKI -----------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/search/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'search');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/label/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/update/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'update');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/delrev/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'deleteRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/delete/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'delete');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/page/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'view');

// ---------- Downloads ------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/label/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/get/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'download');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'submit');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/delete/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'delete');

// ---------- CODE REVIEW --------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/getpatch/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'getPatch');


// ---------- ADMIN --------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'admin');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/issues/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminIssues');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/downloads/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminDownloads');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/wiki/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminWiki');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/source/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminSource');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/members/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminMembers');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/tabs/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminTabs');

// ---------- API ----------------------------------------

$ctl[] = array('regex' => '#^/help/api/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'faqApi');

$ctl[] = array('regex' => '#^/api/p/([\-\w]+)/issues/$#',
               'base' => $base,
               'model' => 'IDF_Views_Api',
               'method' => 'issuesIndex');

$ctl[] = array('regex' => '#^/api/p/([\-\w]+)/issues/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Api',
               'method' => 'issueCreate');

// ---------- FORGE ADMIN --------------------------------

$ctl[] = array('regex' => '#^/admin/projects/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projects');

$ctl[] = array('regex' => '#^/admin/projects/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projectUpdate');

$ctl[] = array('regex' => '#^/admin/projects/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projectCreate');

$ctl[] = array('regex' => '#^/admin/projects/(\d+)/delete/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projectDelete');

$ctl[] = array('regex' => '#^/admin/users/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'users');

$ctl[] = array('regex' => '#^/admin/users/notvalid/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'usersNotValidated');

$ctl[] = array('regex' => '#^/admin/users/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'userUpdate');

// ---------- UTILITY VIEWS -------------------------------

$ctl[] = array('regex' => '#^/register/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'register');

$ctl[] = array('regex' => '#^/register/k/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'registerConfirmation');

$ctl[] = array('regex' => '#^/register/ik/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'registerInputKey');

$ctl[] = array('regex' => '#^/password/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'passwordRecoveryAsk');

$ctl[] = array('regex' => '#^/password/ik/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'passwordRecoveryInputCode');

$ctl[] = array('regex' => '#^/password/k/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'passwordRecovery');

$ctl[] = array('regex' => '#^/preferences/email/ik/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'changeEmailInputKey');

$ctl[] = array('regex' => '#^/preferences/email/ak/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'changeEmailDo');

return $ctl;
