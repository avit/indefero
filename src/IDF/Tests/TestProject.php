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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');

/**
 * Test the creation/modification of a project.
 */
class IDF_Tests_TestProject extends UnitTestCase 
{
 
    public function __construct() 
    {
        parent::__construct('Test creation/modification of a project.');
    }

    public function tearDown()
    {
        foreach (Pluf::factory('IDF_Project')->getList() as $proj) {
            $proj->delete();
        }
    }

    public function testCreate()
    {
        $gproj = Pluf::factory('IDF_Project')->getList();
        $this->assertEqual(0, $gproj->count());
        $project = new IDF_Project();
        $project->name = 'Test project';
        $project->shortname = 'test';
        $project->description = 'This is a test project.';
        $project->create();
        $id = $project->id;
        $p2 = new IDF_Project($id);
        $this->assertEqual($p2->shortname, $project->shortname);
    }
    
    public function testMultipleCreate()
    {
        $project = new IDF_Project();
        $project->name = 'Test project';
        $project->shortname = 'test';
        $project->description = 'This is a test project.';
        $project->create();
        try {
            $project = new IDF_Project();
            $project->name = 'Test project';
            $project->shortname = 'test';
            $project->description = 'This is a test project.';
            $project->create();
            // if here it as failed
            $this->fail('It should not be possible to create 2 projects with same shortname');
        } catch (Exception $e) {
            $this->pass();
        }
    }
}