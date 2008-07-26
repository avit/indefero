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
     * @param string Tree ('HEAD')
     * @param string Base folder ('')
     * @return array 
     */
    public function filesInTree($tree='HEAD', $basefolder='')
    {
        if (is_object($basefolder)) {
            $base = $basefolder;
        } else if (
                   $basefolder !=  ''
                   and
            (
             (false === ($base=$this->getFileInfo($basefolder, $tree)))
             or
             ($base->type != 'tree')
             )) {
            throw new Exception(sprintf('Base folder "%s" not found.', $basefolder));
        } else {
            // no base
            $base = (object) array('file' => '',
                                   'hash' => $tree);
        }
        
        $res = array();
        $out = array();
        $cmd = sprintf('GIT_DIR=%s git-ls-tree -t -l %s', $this->repo, $base->hash);
        exec($cmd, &$out);
        $rawlog = array();
        foreach ($this->getBranches() as $br) {
            $cmd = sprintf('GIT_DIR=%s git log --raw --abbrev=40 --pretty=oneline %s',
                           $this->repo, $br);
            exec($cmd, &$rawlog);
        }
        $rawlog = implode("\n", array_reverse($rawlog));
        $current_dir = getcwd();
        chdir(substr($this->repo, 0, -5));
        foreach ($out as $line) {
            list($perm, $type, $hash, $size, $file) = preg_split('/ |\t/', $line, 5, PREG_SPLIT_NO_EMPTY);
            $matches = array();
            $date = '1970-01-01 12:00:00';
            $log = '';
            if ($type == 'blob' and preg_match('/^\:\d{6} \d{6} [0-9a-f]{40} '.$hash.' .*^([0-9a-f]{40})/msU',
                           $rawlog, &$matches)) {
                $_c = $this->getCommit($matches[1]);
                $date = $_c->date;
                $log = $_c->title;
            }
            $res[] = (object) array('perm' => $perm, 'type' => $type, 
                                    'size' => $size, 'hash' => $hash, 
                                    'fullpath' => ($base->file) ? $base->file.'/'.$file : $file,
                                    'log' => $log, 'date' => $date,
                                    'file' => $file);
        }
        chdir($current_dir);
        return $res;
    }

    /**
     * Get the file info.
     *
     * @param string File
     * @param string Tree ('HEAD')
     * @return false Information
     */
    public function getFileInfo($totest, $tree='HEAD')
    {
        $cmd_tmpl = 'GIT_DIR=%s git-ls-tree -r -t -l %s';
        $cmd = sprintf($cmd_tmpl, $this->repo, $tree);
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

    /**
     * Get commit details.
     *
     * @param string Commit ('HEAD').
     * @return array Changes.
     */
    public function getCommit($commit='HEAD')
    {
        $cmd = sprintf('GIT_DIR=%s git show --date=iso --pretty=medium %s',
                       escapeshellarg($this->repo), $commit);
        $out = array();
        exec($cmd, &$out);
        $log = array();
        $change = array();
        $inchange = false;
        foreach ($out as $line) {
            if (!$inchange and 0 === strpos($line, 'diff --git a')) {
                $inchange = true;
            }
            if ($inchange) {
                $change[] = $line;
            } else {
                $log[] = $line;
            }
        }
        $out = self::parseLog($log);
        $out[0]->changes = $change;
        return $out[0];
    }


    /**
     * Get latest changes.
     *
     * @param string Tree ('HEAD').
     * @param int Number of changes (10).
     * @return array Changes.
     */
    public function getChangeLog($tree='HEAD', $n=10)
    {
        $format = 'commit %H%nAuthor: %an <%ae>%nTree: %T%nDate: %ai%n%n%s%n%n%b';
        if ($n === null) $n = '';
        else $n = ' -'.$n;
        $cmd = sprintf('GIT_DIR=%s git log%s --date=iso --pretty=format:\'%s\' %s',
                       escapeshellarg($this->repo), $n, $format, $tree);
        $out = array();
        exec($cmd, &$out);
        //print_r($cmd);
        return self::parseLog($out, 4);
    }

    /**
     * Parse the log lines of a --pretty=medium log output.
     *
     * @param array Lines.
     * @param int Number of lines in the headers (3)
     * @return array Change log.
     */
    public static function parseLog($lines, $hdrs=3)
    {
        $res = array();
        $c = array();
        $i = 0;
        $hdrs += 2;
        foreach ($lines as $line) {
            $i++;
            if (0 === strpos($line, 'commit')) {
                if (count($c) > 0) {
                    $c['full_message'] = trim($c['full_message']);
                    $res[] = (object) $c;
                }
                $c = array();
                $c['commit'] = trim(substr($line, 7));
                $c['full_message'] = '';
                $i=1;
                continue;
            }
            if ($i == $hdrs) {
                $c['title'] = trim($line);
                continue;
            }
            $match = array();
            if (preg_match('/(\S+)\s*:\s*(.*)/', $line, $match)) {
                $match[1] = strtolower($match[1]);
                $c[$match[1]] = trim($match[2]);
                if ($match[1] == 'date') {
                    $c['date'] = gmdate('Y-m-d H:i:s', strtotime($match[2]));
                }
                continue;
            }
            if ($i > ($hdrs+1)) {
                $c['full_message'] .= trim($line)."\n";
                continue;
            }
        }
        $c['full_message'] = trim($c['full_message']);
        $res[] = (object) $c;
        return $res;
    }

}