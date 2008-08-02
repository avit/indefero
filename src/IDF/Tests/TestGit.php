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

/**
 * Test the git class.
 */
class IDF_Tests_TestGit extends UnitTestCase 
{
 
    public function __construct() 
    {
        parent::__construct('Test the git class.');
    }

    public function testParseLog()
    {
        $log_lines = preg_split("/\015\012|\015|\012/", file_get_contents(dirname(__FILE__).'/test-log.txt'));
        $log = IDF_Git::parseLog($log_lines, 3);
        $this->assertEqual('Fixed the middleware to correctly return a 404 error if the project is', $log[0]->title);

    }
}