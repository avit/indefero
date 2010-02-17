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
class IDF_Scm_Mercurial extends IDF_Scm
{
    public function __construct($repo, $project=null)
    {
        $this->repo = $repo;
        $this->project = $project;
    }

    public function getRepositorySize()
    {
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').'du -skD '
            .escapeshellarg($this->repo);
        $out = explode(' ', 
                       self::shell_exec('IDF_Scm_Mercurial::getRepositorySize',
                                        $cmd), 
                       2);
        return (int) $out[0]*1024;
    }

    public static function factory($project)
    {
        $rep = sprintf(Pluf::f('mercurial_repositories'), $project->shortname);
        return new IDF_Scm_Mercurial($rep, $project);
    }

    public function isAvailable()
    {
        try {
            $branches = $this->getBranches();
        } catch (IDF_Scm_Exception $e) {
            return false;
        }
        return (count($branches) > 0);
    }

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

    public function getMainBranch()
    {
        return 'tip';
    }

    public static function getAnonymousAccessUrl($project)
    {
        return sprintf(Pluf::f('mercurial_remote_url'), $project->shortname);
    }

    public static function getAuthAccessUrl($project, $user)
    {
        return sprintf(Pluf::f('mercurial_remote_url'), $project->shortname);
    }

    public function isValidRevision($rev)
    {
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' log -R %s -r %s',
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::isValidRevision', $cmd, $out, $ret);
        return ($ret == 0) && (count($out) > 0);
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
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' log -R %s -r %s',
                       escapeshellarg($this->repo),
                       escapeshellarg($hash));
        $ret = 0; 
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::testHash', $cmd, $out, $ret);
        return ($ret != 0) ? false : 'commit'; 
    }

    public function getTree($commit, $folder='/', $branch=null)
    {
        // now we grab the info about this commit including its tree.
        $folder = ($folder == '/') ? '' : $folder;
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
        $cmd_tmpl = Pluf::f('hg_path', 'hg').' manifest -R %s --debug -r %s';
        $cmd = sprintf($cmd_tmpl, escapeshellarg($this->repo), $tree, ($recurse) ? '' : ''); 
        $out = array();
        $res = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getTreeInfo', $cmd, $out);
        $tmp_hack = array();
        while (null !== ($line = array_pop($out))) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim($file);
            $dir = explode('/', $file, -1);
            $tmp = '';
            for ($i=0, $n=count($dir); $i<$n; $i++) {
                if ($i > 0) {
                    $tmp .= '/';
                }
                $tmp .= $dir[$i];
                if (!isset($tmp_hack["empty\t000\t\t$tmp/"])) {
                    $out[] = "empty\t000\t\t$tmp/";
                    $tmp_hack["empty\t000\t\t$tmp/"] = 1;
                }
            }
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
            $fullpath = ($folder) ? $folder.'/'.$file : $file;
            $efullpath = self::smartEncode($fullpath);
            $res[] = (object) array('perm' => $perm, 'type' => $type, 
                                    'hash' => $hash, 'fullpath' => $fullpath,
                                    'efullpath' => $efullpath, 'file' => $file);
        }
        return $res;
    }

    public function getPathInfo($totest, $commit='tip')
    {
        $cmd_tmpl = Pluf::f('hg_path', 'hg').' manifest -R %s --debug -r %s';
        $cmd = sprintf($cmd_tmpl, escapeshellarg($this->repo), $commit); 
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getPathInfo', $cmd, $out);
        $tmp_hack = array();
        while (null !== ($line = array_pop($out))) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim($file);
            $dir = explode('/', $file, -1);
            $tmp = '';
            for ($i=0, $n=count($dir); $i<$n; $i++) {
                if ($i > 0) {
                    $tmp .= '/';
                }
                $tmp .= $dir[$i];
                if ($tmp == $totest) {
                    $pathinfo = pathinfo($totest);
                    return (object) array('perm' => '000', 'type' => 'tree', 
                                          'hash' => $hash, 
                                          'fullpath' => $totest,
                                          'file' => $pathinfo['basename'],
                                          'commit' => $commit
                                          );
                }
                if (!isset($tmp_hack["empty\t000\t\t$tmp/"])) {
                    $out[] = "empty\t000\t\t$tmp/";
                    $tmp_hack["empty\t000\t\t$tmp/"] = 1;
                }
            }
            if (preg_match('/^(.*)\/$/', $file, $match)) {
                $type = 'tree';
                $file = $match[1];
            } else {
                $type = 'blob';
            }
            if ($totest == $file) {
                $pathinfo = pathinfo($totest);
                return (object) array('perm' => $perm, 'type' => $type, 
                                      'hash' => $hash, 
                                      'fullpath' => $totest,
                                      'file' => $pathinfo['basename'],
                                      'commit' => $commit
                                      );
            }
        }
        return false;
    }
      
    public function getFile($def, $cmd_only=false)
    {
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' cat -R %s -r %s %s',
                       escapeshellarg($this->repo), 
                       escapeshellarg($def->commit), 
                       escapeshellarg($this->repo.'/'.$def->fullpath));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        return ($cmd_only) ? 
            $cmd : self::shell_exec('IDF_Scm_Mercurial::getFile', $cmd);
    }

    /**
     * Get the branches.
     *
     * @return array Branches.
     */
    public function getBranches()
    {
        if (isset($this->cache['branches'])) {
            return $this->cache['branches'];
        }
        $out = array();
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' branches -R %s', 
                       escapeshellarg($this->repo));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getBranches', $cmd, $out);
        $res = array();
        foreach ($out as $b) {
            preg_match('/(\S+).*\S+:(\S+)/', $b, $match);
            $res[$match[1]] = '';
        }
        $this->cache['branches'] = $res;
        return $res;
    }

    /**
     * Get the tags.
     *
     * @return array Tags.
     */
    public function getTags()
    {
        if (isset($this->cache['tags'])) {
            return $this->cache['tags'];
        }
        $out = array();
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' tags -R %s', 
                       escapeshellarg($this->repo));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getTags', $cmd, $out);
        $res = array();
        foreach ($out as $b) {
            preg_match('/(\S+).*\S+:(\S+)/', $b, $match);
            $res[$match[1]] = '';
        }
        $this->cache['tags'] = $res;
        return $res;
    }

    public function inBranches($commit, $path)
    {
        return (in_array($commit, array_keys($this->getBranches()))) 
                ? array($commit) : array();
    }

    public function inTags($commit, $path)
    {
        return (in_array($commit, array_keys($this->getTags()))) 
                ? array($commit) : array();
    }

    /**
     * Get commit details.
     *
     * @param string Commit ('HEAD')
     * @param bool Get commit diff (false)
     * @return array Changes
     */
    public function getCommit($commit, $getdiff=false)
    {
        if (!$this->isValidRevision($commit)) {
            return false;
        }
        $tmpl = ($getdiff) ? 
            Pluf::f('hg_path', 'hg').' log -p -r %s -R %s' : Pluf::f('hg_path', 'hg').' log -r %s -R %s';
        $cmd = sprintf($tmpl, 
                       escapeshellarg($commit), escapeshellarg($this->repo));
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getCommit', $cmd, $out);
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
     * Check if a commit is big.
     *
     * @param string Commit ('HEAD')
     * @return bool The commit is big
     */
    public function isCommitLarge($commit='HEAD')
    {
        return false;
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
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' log -R %s -l%s ', escapeshellarg($this->repo), $n, $commit);
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getChangeLog', $cmd, $out);
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
        $c['tree'] = !empty($c['commit']) ? trim($c['commit']) : '';
        $c['full_message'] = !empty($c['full_message']) ? trim($c['full_message']) : '';
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
        return sprintf(Pluf::f('idf_exec_cmd_prefix', '').
                       Pluf::f('hg_path', 'hg').' archive --type=zip -R %s -r %s -',
                       escapeshellarg($this->repo),
                       escapeshellarg($commit));
    }
}
