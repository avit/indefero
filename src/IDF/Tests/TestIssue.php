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
class IDF_Tests_TestIssue extends UnitTestCase 
{
    public $projects = array();
    public $users = array();

    public function __construct() 
    {
        parent::__construct('Test creation/modification of issues.');
    }

    /**
     * Create 2 projects to work with and 2 users.
     */
    public function setUp()
    {
        $this->projects = array();
        $this->users = array();
        for ($i=1;$i<3;$i++) {
            $project = new IDF_Project();
            $project->name = 'Test project '.$i;
            $project->shortname = 'test'.$i;
            $project->description = sprintf('This is a test project %d.', $i);
            $project->create();
            $this->projects[] = $project;
            $user = new Pluf_User();
            $user->last_name = 'user'.$i;
            $user->login = 'user'.$i;
            $user->email = 'user'.$i.'@example.com';
            $user->create();
            $this->users[] = $user;
        }
    }


    public function tearDown()
    {
        // This will drop cascading issues, comments and tags.
        foreach ($this->projects as $proj) {
            $proj->delete();
        }
        foreach ($this->users as $u) {
            $u->delete();
        }
    }

    public function testCreate()
    {
        $issue = new IDF_Issue();
        $issue->project = $this->projects[0];
        $issue->summary = 'This is a test issue';
        $issue->submitter = $this->users[0];
        $issue->create();
        $this->assertEqual(1, $issue->id);
        $this->assertIdentical(null, $issue->get_owner());
        $this->assertNotIdentical(null, $issue->get_submitter());
    }

    public function testCreateMultiple()
    {
        for ($i=1;$i<11;$i++) {
            $issue = new IDF_Issue();
            $issue->project = $this->projects[0];
            $issue->summary = 'This is a test issue '.$i;
            $issue->submitter = $this->users[0];
            $issue->owner = $this->users[1];
            $issue->create();
        }
        for ($i=11;$i<16;$i++) {
            $issue = new IDF_Issue();
            $issue->project = $this->projects[1];
            $issue->summary = 'This is a test issue '.$i;
            $issue->submitter = $this->users[1];
            $issue->create();
        }
        $this->assertEqual(10, 
                           $this->projects[0]->get_issues_list()->count());
        $this->assertEqual(5, 
                           $this->projects[1]->get_issues_list()->count());
        $this->assertEqual(5, 
                           $this->users[1]->get_submitted_issue_list()->count());
        $this->assertEqual(10, 
                           $this->users[0]->get_submitted_issue_list()->count());
        $this->assertEqual(10, 
                           $this->users[1]->get_owned_issue_list()->count());
        $this->assertEqual(0, 
                           $this->users[1]->get_owned_issue_list(array('filter' => 'project='.(int)$this->projects[1]->id))->count());
        $this->assertEqual(10, 
                           $this->users[1]->get_owned_issue_list(array('filter' => 'project='.(int)$this->projects[0]->id))->count());
    }

    public function testAddIssueComment()
    {
        $issue = new IDF_Issue();
        $issue->project = $this->projects[0];
        $issue->summary = 'This is a test issue';
        $issue->submitter = $this->users[0];
        $issue->create();
        $ic = new IDF_IssueComment();
        $ic->issue = $issue;
        $ic->submitter = $this->users[0];
        $ic->content = 'toto';
        $changes = array('s' => 'New summary',
                         'st' => 'Active',
                         't' => '-OS:Linux OS:Windows');
        $ic->changes = $changes;
        $ic->create();
        $comments = $issue->get_comments_list();
        $this->assertEqual($changes, $comments[0]->changes);
    }
}