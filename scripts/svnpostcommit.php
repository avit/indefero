<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008-2010 Céondo Ltd and contributors.
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

/**
 * This script will send the notifications after a push in your 
 * repository.
 */

require dirname(__FILE__).'/../src/IDF/conf/path.php';
require 'Pluf.php';
Pluf::start(dirname(__FILE__).'/../src/IDF/conf/idf.php');
Pluf_Dispatcher::loadControllers(Pluf::f('idf_views'));

/**
 * [signal]
 *
 * svnpostcommit.php::run
 *
 * [sender]
 *
 * svnpostcommit.php
 *
 * [description]
 *
 * This signal allows an application to perform a set of tasks on a
 * post commit of a subversion repository.
 *
 * [parameters]
 *
 * array('repo_dir' => '/path/to/subversion/repository',
 *       'revision' => 1234,
 *       'env' => array_merge($_ENV, $_SERVER));
 *
 */
$params = array('repo_dir' => $argv[1],
                'revision' => $argv[2],
                'env' => array_merge($_ENV, $_SERVER));
Pluf_Signal::send('svnpostcommit.php::run', 'svnpostcommit.php', $params);



