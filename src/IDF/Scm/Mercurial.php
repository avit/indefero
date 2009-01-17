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
 * Mercurial utils.
 *
 */
class IDF_Scm_Mercurial
{
    public $repo = '';
    
    public function __construct($repo)
    {
        $this->repo = $repo;
    }

    /**
     * Given the string describing the author from the log find the
     * author in the database.
     *
     * @param string Author
     * @return mixed Pluf_User or null
     */
    public function findAuthor($author)
    {
        // We extract the email.
        $match = array();
        if (!preg_match('/<(.*)>/', $author, $match)) {
            return null;
        }
        $sql = new Pluf_SQL('email=%s', array($match[1]));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        return ($users->count() > 0) ? $users[0] : null;
    }

    /**
     * Returns the URL of the git daemon.
     *
     * @param IDF_Project
     * @return string URL
     */
    public static function getRemoteAccessUrl($project)
    {
        return sprintf(Pluf::f('mercurial_remote_url'), $project->shortname);
    }

    /**
     * Returns this object correctly initialized for the project.
     *
     * @param IDF_Project
     * @return IDF_Scm_Git
     */
    public static function factory($project)
    {
        $rep = sprintf(Pluf::f('mercurial_repositories'), $project->shortname);
        return new IDF_Scm_Mercurial($rep);
    }

    /**
     * Test a given object hash.
     *
     * @param string Object hash.
     * @param null to be svn client compatible
     * @return mixed false if not valid or 'blob', 'tree', 'commit'
     */
    public function testHash($hash, $dummy=null)
    {
        $cmd = sprintf('hg log -R %s -r %s',
                       escapeshellarg($this->repo),
                       escapeshellarg($hash));
        $ret = 0; 
        $out = array();
        IDF_Scm::exec($cmd, $out, $ret);
        return ($ret != 0) ? false : 'commit'; 
    }

    /**
     * Given a commit hash returns an array of files in it.
     *
     * A file is a class with the following properties:
     *
     * 'perm', 'type', 'size', 'hash', 'file'
     *
     * @param string Commit ('HEAD')
     * @param string Base folder ('')
     * @return array 
     */
    public function filesAtCommit($commit='tip', $folder='')
    {
        // now we grab the info about this commit including its tree.
        $co = $this->getCommit($commit);
        if ($folder) {
            // As we are limiting to a given folder, we need to find
            // the tree corresponding to this folder.
            $found = false;
            foreach ($this->getTreeInfo($co->tree, true, '', true) as $file) {
                if ($file->type == 'tree' and $file->file == $folder) {
                    $found = true;
                    break;
                }
            } 
            if (!$found) {
                throw new Exception(sprintf(__('Folder %1$s not found in commit %2$s.'), $folder, $commit));
            }
        }
        $res = $this->getTreeInfo($commit, $recurse=true, $folder);
        return $res;
    }

    /**
     * Get the tree info.
     *
     * @param string Tree hash 
     * @param bool Do we recurse in subtrees (true)
     * @return array Array of file information.
     */
    public function getTreeInfo($tree, $recurse=true, $folder='', $root=false)
    {
        if ('commit' != $this->testHash($tree)) {
            throw new Exception(sprintf(__('Not a valid tree: %s.'), $tree));
        }
        $cmd_tmpl = 'hg manifest -R %s --debug -r %s';
        $cmd = sprintf($cmd_tmpl, escapeshellarg($this->repo), $tree, ($recurse) ? '' : ''); 
        $out = array();
        $res = array();
        IDF_Scm::exec($cmd, $out);
        $out_hack = array();
        foreach ($out as $line) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim($file);
            $dir = explode('/', $file, -1);
            $tmp = '';
            for ($i=0; $i < count($dir); $i++) {
                if ($i > 0) {
                    $tmp .= '/';
                }
                $tmp .= $dir[$i];
                if (!in_array("empty\t000\t\t$tmp/", $out_hack))
                    $out_hack[] = "empty\t000\t\t$tmp/";
            }
            $out_hack[] = "$hash\t$perm\t$exec\t$file";
        }
        foreach ($out_hack as $line) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim($file);
            if (preg_match('/^(.*)\/$/', $file, $match)) {
                $type = 'tree';
                $file = $match[1];
            } else {
                $type = 'blob';
            }
            if (!$root and !$folder and preg_match('/^.*\/.*$/', $file)) {
                continue;
            }
            if ($folder) {
                preg_match('|^'.$folder.'[/]?([^/]+)?$|', $file,$match);
                if (count($match) > 1) {
                    $file = $match[1];
                } else {
                    continue;
                }
            }
            $res[] = (object) array('perm' => $perm, 'type' => $type, 
                                    'hash' => $hash, 'fullpath' => ($folder) ? $folder.'/'.$file : $file,
                                    'file' => $file);
        }
        return $res;
    }

    /**
     * Get the file info.
     *
     * @param string Commit ('HEAD')
     * @return false Information
     */
    public function getFileInfo($totest, $commit='tip')
    {
        $cmd_tmpl = 'hg manifest -R %s --debug -r %s';
        $cmd = sprintf($cmd_tmpl, escapeshellarg($this->repo), $commit); 
        $out = array();
        $res = array();
        IDF_Scm::exec($cmd, $out);
        $out_hack = array();
        foreach ($out as $line) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim($file);
            $dir = explode('/', $file, -1);
            $tmp = '';
            for ($i=0; $i < count($dir); $i++) {
                if ($i > 0) {
                    $tmp .= '/';
                }
                $tmp .= $dir[$i];
                if (!in_array("empty\t000\t\t$tmp/", $out_hack)) {
                    $out_hack[] = "emtpy\t000\t\t$tmp/";
                }
            }
            $out_hack[] = "$hash\t$perm\t$exec\t$file";
        }

        foreach ($out_hack as $line) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim ($file);
            if (preg_match('/^(.*)\/$/', $file, $match)) {
                $type = 'tree';
                $file = $match[1];
            } else {
                $type = 'blob';
            }
            if ($totest == $file) {
                return (object) array('perm' => $perm, 'type' => $type, 
                                      'hash' => $hash, 
                                      'file' => $file,
                                      'commit' => $commit
                                      );

            }
        }
        return false;
    }
      
    /**
     * Get a blob.
     *
     * @param string request_file_info
     * @param null to be svn client compatible
     * @return string Raw blob
     */
    public function getBlob($request_file_info, $dummy=null)
    {
        return IDF_Scm::shell_exec(sprintf('hg cat -R %s -r %s %s',
                                           escapeshellarg($this->repo), 
                                           $dummy,
                                           escapeshellarg($this->repo . '/' . $request_file_info->file)));
    }

    /**
     * Get the branches.
     *
     * @return array Branches.
     */
    public function getBranches()
    {
        $out = array();
        IDF_Scm::exec(sprintf('hg branches -R %s', 
                              escapeshellarg($this->repo)), $out);
        $res = array();
        foreach ($out as $b) {
            preg_match('/(\S+).*\S+:(\S+)/', $b, $match);
            $res[] = $match[1];
        }
        return $res;
    }

    /**
     * Get commit details.
     *
     * @param string Commit ('HEAD').
     * @return array Changes.
     */
    public function getCommit($commit='tip')
    {

        $cmd = sprintf('hg log -p -r %s -R %s', escapeshellarg($commit), escapeshellarg($this->repo));
        $out = array();
        IDF_Scm::exec($cmd, $out);
        $log = array();
        $change = array();
        $inchange = false;
        foreach ($out as $line) {
            if (!$inchange and 0 === strpos($line, 'diff -r')) {
                $inchange = true;
            }
            if ($inchange) {
                $change[] = $line;
            } else {
                $log[] = $line;
            }
        }
        $out = self::parseLog($log, 6);
        $out[0]->changes = implode("\n", $change);
        return $out[0];
    }

    /**
     * Get commit size.
     *
     * Get the sum of all the added/removed lines and the number of
     * affected files.
     *
     * @param string Commit ('HEAD')
     * @return array array(added, removed, affected)
     */
    public function getCommitSize($commit='HEAD')
    {
        return array(0, 0, 0);
    }

    /**
     * Get latest changes.
     *
     * @param string Commit ('HEAD').
     * @param int Number of changes (10).
     * @return array Changes.
     */
    public function getChangeLog($commit='tip', $n=10)
    {
        $cmd = sprintf('hg log -R %s -l%s ', escapeshellarg($this->repo), $n, $commit);
        $out = array();
        IDF_Scm::exec($cmd, $out);
        return self::parseLog($out, 6);
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
        $hdrs += 1;
        foreach ($lines as $line) {
            $i++;
            if (0 === strpos($line, 'changeset:')) {
                if (count($c) > 0) {
                    $c['full_message'] = trim($c['full_message']);
                    $res[] = (object) $c;
                }
                $c = array();
                $c['commit'] = substr(strrchr($line, ':'), 1);
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
                if ($match[1] == 'user') {
                    $c['author'] = $match[2];
                } elseif ($match[1] == 'summary') {
                    $c['title'] = $match[2];
                } else {
                    $c[$match[1]] = trim($match[2]);
                }
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
        $c['tree'] = $c['commit'];
        $c['full_message'] = trim($c['full_message']);
        $res[] = (object) $c;
        return $res;
    }

    /**
     * Generate the command to create a zip archive at a given commit.
     *
     * @param string Commit
     * @param string Prefix ('git-repo-dump')
     * @return string Command
     */
    public function getArchiveCommand($commit, $prefix='')
    {
        return sprintf('hg archive --type=zip -R %s -r %s -',
                       escapeshellarg($this->repo),
                       escapeshellarg($commit));
    }
}
