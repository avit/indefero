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
               'method' => 'login');

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

$ctl[] = array('regex' => '#^/p/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'home');

$ctl[] = array('regex' => '#^/p/(\w+)/issues/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/(\w+)/issues/(\d+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/(\w+)/issues/status/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'listStatus');

$ctl[] = array('regex' => '#^/p/(\w+)/issues/label/(\d+)/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/(\w+)/issues/create/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/(\w+)/issues/my/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Issue',
               'method' => 'myIssues');

// ---------- GIT ----------------------------------------

$ctl[] = array('regex' => '#^/p/(\w+)/source/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/(\w+)/source/tree/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'treeBase');

$ctl[] = array('regex' => '#^/p/(\w+)/source/tree/(\w+)/(.*)$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'tree');

$ctl[] = array('regex' => '#^/p/(\w+)/source/changes/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'changeLog');

$ctl[] = array('regex' => '#^/p/(\w+)/source/commit/(\w+)/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Source',
               'method' => 'commit');


// ---------- ADMIN --------------------------------------

$ctl[] = array('regex' => '#^/p/(\w+)/admin/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'admin');

$ctl[] = array('regex' => '#^/p/(\w+)/admin/issues/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminIssues');

$ctl[] = array('regex' => '#^/p/(\w+)/admin/members/$#',
               'base' => $base,
               'priority' => 4,
               'model' => 'IDF_Views_Project',
               'method' => 'adminMembers');

return $ctl;
