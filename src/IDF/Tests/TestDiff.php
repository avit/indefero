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
 * Test the diff parser.
 */
class IDF_Tests_TestDiff extends UnitTestCase 
{
 
    public function __construct() 
    {
        parent::__construct('Test the diff parser.');
    }

    public function testGetFile()
    {
        $lines = array(
                       'diff --git a/src/IDF/Form/Register.php b/src/IDF/Form/Register.php',
                       'diff --git a/src/IDF/Form/RegisterConfirmation.php b/src/IDF/Form/RegisterConfirmation.php',
                       'diff --git a/src/IDF/Form/RegisterInputKey.php b/src/IDF/Form/RegisterInputKey.php',
                       'diff --git a/src/IDF/Views.php b/src/IDF/Views.php',
                       'diff --git a/src/IDF/conf/views.php b/src/IDF/conf/views.php',
                       );
        $files = array(
                       'src/IDF/Form/Register.php',
                       'src/IDF/Form/RegisterConfirmation.php',
                       'src/IDF/Form/RegisterInputKey.php',
                       'src/IDF/Views.php',
                       'src/IDF/conf/views.php',
                       );
        $i = 0;
        foreach ($lines as $line) {
            $this->assertEqual($files[$i], IDF_Diff::getFile($line));
            $i++;
        }
    }

    public function testBinaryDiff()
    {
        $diff_content = file_get_contents(dirname(__FILE__).'/test-diff.diff');
        $orig = file_get_contents(dirname(__FILE__).'/test-diff-view.html');
        $diff = new IDF_Diff($diff_content);
        $diff->parse();
        $def = $diff->files['src/IDF/templates/idf/issues/view.html'];

        $orig_lines = preg_split("/\015\012|\015|\012/", $orig);
        $merged = $diff->mergeChunks($orig_lines, $def, 10);
        $lchunk = end($merged);
        $lline = end($lchunk);
        $this->assertEqual(array('', '166', '{/if}{/block}'),
                           $lline);
        //print_r($diff->mergeChunks($orig_lines, $def, 10));
    }
}