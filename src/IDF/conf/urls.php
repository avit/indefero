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
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'index');

$ctl[] = array('regex' => '#^/login/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'login',
               'name' => 'login_view');

$ctl[] = array('regex' => '#^/preferences/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_User',
               'method' => 'myAccount');

$ctl[] = array('regex' => '#^/u/(.*)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_User',
               'method' => 'view');

$ctl[] = array('regex' => '#^/register/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'register');

$ctl[] = array('regex' => '#^/register/k/(.*)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'registerConfirmation');

$ctl[] = array('regex' => '#^/register/ik/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'registerInputKey');

$ctl[] = array('regex' => '#^/logout/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'logout');

$ctl[] = array('regex' => '#^/help/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'faq');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'home');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/timeline/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'timeline');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/search/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'search');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/(\d+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/(\d+)/star/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'star');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/status/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'listStatus');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/label/(\d+)/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/create/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/my/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'myIssues');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/attachment/(\d+)/(.*)$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'getAttachment');

// ---------- SCM ----------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/tree/([^/]+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'treeBase');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/tree/([^/]+)/(.*)$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'tree');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/changes/([^/]+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'changeLog');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/commit/([^/]+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'commit');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/ddiff/([^/]+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'downloadDiff');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/download/([^/]+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'download');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/file/([^/]+)/(.*)$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'getFile');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/treerev/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source_Svn',
               'method' => 'treeRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/changesrev/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source_Svn',
               'method' => 'changelogRev');

// ---------- WIKI -----------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Wiki',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/create/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Wiki',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/update/(.*)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Wiki',
               'method' => 'update');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/delrev/(\d+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Wiki',
               'method' => 'deleteRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/page/(.*)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Wiki',
               'method' => 'view');

// ---------- Downloads ------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Download',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/label/(\d+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Download',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Download',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/get/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Download',
               'method' => 'download');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/create/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Download',
               'method' => 'submit');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/delete/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Download',
               'method' => 'delete');


// ---------- ADMIN --------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'admin');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/issues/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminIssues');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/downloads/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminDownloads');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/wiki/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminWiki');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/source/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminSource');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/members/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminMembers');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/tabs/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminTabs');

// ---------- API ----------------------------------------

$ctl[] = array('regex' => '#^/help/api/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views',
               'method' => 'faqApi');

$ctl[] = array('regex' => '#^/api/p/([\-\w]+)/issues/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Api',
               'method' => 'issuesIndex');

$ctl[] = array('regex' => '#^/api/p/([\-\w]+)/issues/create/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Api',
               'method' => 'issueCreate');

return $ctl;
