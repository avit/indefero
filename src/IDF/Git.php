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
 * Git utils.
 */
class IDF_Git
{
    public $repo = '';
    
    public function __construct($repo)
    {
        $this->repo = $repo;
    }

    /**
     * Given a commit hash (or a branch) returns an array of files in
     * it.
     *
     * A file is a class with the following properties:
     *
     * 'perm', 'type', 'size', 'hash', 'file'
     *
     * @param string Commit/Branch ('HEAD')
     * @param string Base folder ('')
     * @return array 
     */
    public function filesInTree($commit='HEAD', $basefolder='')
    {
        if (is_object($basefolder)) {
            $base = $basefolder;
        } else if ($basefolder != '' 
            and 
            (
             (false === ($base=$this->getFileInfo($basefolder, $commit)))
             or
             ($base->type != 'tree')
             )) {
            throw new Exception(sprintf('Base folder "%s" not found.', $basefolder));
        } else {
            // no base
            $base = (object) array('file' => '',
                                   'hash' => $commit);
        }
        
        $res = array();
        $out = array();
        $cmd = sprintf('GIT_DIR=%s git-ls-tree -t -l %s', $this->repo, $base->hash);
        exec($cmd, &$out);
        $current_dir = getcwd();
        chdir(substr($this->repo, 0, -5));
        foreach ($out as $line) {
            list($perm, $type, $hash, $size, $file) = preg_split('/ |\t/', $line, 5, PREG_SPLIT_NO_EMPTY);
            $cm = array();
            $cmd = sprintf('GIT_DIR=%s git log -1 --pretty=format:\'%%H %%at %%s\' %s -- %s', $this->repo, $commit, ($base->file) ? $base->file.'/'.$file : $file);
            exec($cmd, &$cm);
            list($h, $time, $log) = explode(' ', $cm[0], 3);
            $res[] = (object) array('perm' => $perm, 'type' => $type, 
                                    'size' => $size, 'hash' => $hash, 
                                    'fullpath' => ($base->file) ? $base->file.'/'.$file : $file,
                                    'log' => $log, 'time' => $time,
                                    'file' => $file);
        }
        chdir($current_dir);
        return $res;
    }

    /**
     * Get the file info.
     *
     * @param string Tree to test
     * @param string Commit/Branch ('HEAD')
     * @return false or Tree information
     */
    public function getFileInfo($totest, $commit='HEAD')
    {
        $cmd_tmpl = 'GIT_DIR=%s git-ls-tree -r -t -l %s';
        $cmd = sprintf($cmd_tmpl, $this->repo, $commit);
        $out = array();
        exec($cmd, &$out);
        foreach ($out as $line) {
            list($perm, $type, $hash, $size, $file) = preg_split('/ |\t/', $line, 5, PREG_SPLIT_NO_EMPTY);
            if ($totest == $file) {
                return (object) array('perm' => $perm, 'type' => $type, 
                                      'size' => $size, 'hash' => $hash, 
                                      'file' => $file);
            }
        }
        return false;
    }

    /**
     * Get a blob.
     *
     * @param string Blob hash
     * @return string Raw blob
     */
    public function getBlob($hash)
    {
        return shell_exec(sprintf('GIT_DIR=%s git-cat-file blob %s',
                                  $this->repo, $hash));
    }

    /**
     * Get the branches.
     */
    public function getBranches()
    {
        $out = array();
        exec(sprintf('GIT_DIR=%s git branch', $this->repo), &$out);
        $res = array();
        foreach ($out as $b) {
            $res[] = substr($b, 2);
        }
        return $res;
    }
}