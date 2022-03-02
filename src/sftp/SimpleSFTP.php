<?php

/**
 * @author James Standbridge <james.standbridge.git@gmail.com>
 */

namespace JamesStandbridge\SimpleSFTP\sftp;

use phpseclib3\Net\SFTP;
use JamesStandbridge\SimpleSFTP\sftp\Exception\SFTPHostAuthentificationFailed;
use JamesStandbridge\SimpleSFTP\sftp\Exception\SFTPHostNoResponse;
use JamesStandbridge\SimpleSFTP\sftp\StrTest;

use \DateTime;
use \DateInterval;

define("STR_START_WITH", "STR_START_WITH");
define("STR_END_WITH", "STR_END_WITH");
define("STR_CONTAINS", "STR_CONTAINS");

class SimpleSFTP
{
	private SFTP $connexion;

	public function __construct(string $host, string $user, string $password, int $port = 22)
	{
		$this->connection = new SFTP($host, $port);
	
		try {
			$this->connection->getServerPublicHostKey();
		} catch(\Exception $e) {
			throw new SFTPHostNoResponse(sprintf("Couldn't connect to host %s", $host));
		}

		if(!$this->connection->login($user, $password)) {
			throw new SFTPHostAuthentificationFailed(
				sprintf("Couldn't authenticate user %s to host %s", $user, $host)
			);
		}
	}	

	public function handle_archive(string $archive_dirname, ?int $maxDay = null, ?int $maxFiles = null): self
	{
		if(!$maxDay && !$maxFiles) {
			throw new \LogicException(sprintf("You must provide maxDay xor maxFiles"));
		}
		$this->connection->setListOrder("mtime", SORT_DESC);
		$files = array_slice($this->connection->rawlist($archive_dirname), 2);
		$file_index = 0;
		foreach($files as $filename => $fileinfos) {
			if($fileinfos["type"] === 1) {
				$file_index++;
				dump($file_index);
				if($maxFiles) {
					if($file_index > $maxFiles) {
						dump("delete");
						$this->rm("$archive_dirname/$filename");
					}
				} else if ($maxDay) {
					$compare_date = (new DateTime())->sub(new DateInterval("P".$maxDay."D"))->getTimestamp();
					if($fileinfos["mtime"] < $compare_date) {
						$this->rm("$archive_dirname/$filename");
					}
				}	
			}
		}

		return $this;
	}

	public function cd(string $path): self
	{
		$this->connection->chdir($path);
		return $this;
	}

	public function mkdir(string $dirname): bool
	{
		return $this->connection->mkdir($dirname);
	}

	public function rename(string $old_filename, string $new_filename)
	{
		return $this->connection->rename($old_filename, $new_filename);
	}

	public function get_last_file(?string $local_filename = null, ?string $needle = null, string $type = STR_CONTAINS)
	{
		$this->connection->setListOrder("mtime", SORT_DESC);
		$files = array_slice($this->connection->rawlist(), 2);

		if(!$needle) {
			$local_filename = $local_filename ? $local_filename : array_key_first($files);
			return $this->get(array_key_first($files), $local_filename);
		} else {
			foreach($files as $filename => $fileinfos) {
				$valid = false;
				switch($type) {
					case STR_CONTAINS: {
						$valid = StrTest::str_contains($needle, $filename);
						break;
					}
					case STR_START_WITH: {
						$valid = StrTest::str_startwith($needle, $filename);
						break;
					}
					case STR_END_WITH: {
						$valid = StrTest::str_endwith($needle, $filename);
						break;
					}
				}
				if($valid) {
					$local_filename = $local_filename ? $local_filename : $filename;
					return $this->get($filename, $local_filename);
				}
			}
		}
		return false;
	}

	public function rm(string $filename, bool $recursive_mode = false): bool
	{
		if($recursive_mode) {
			$res = $this->recursive_delete($filename, $this->current_dir());
		} else {
			if($this->connection->is_file($filename)) {
				$res = $this->connection->delete($filename);
			} else {
				$res = $this->connection->rmdir($filename);
			}
		}

		return $res;
	}

	public function get_dir(string $remote_dirname, string $local_dirname)
	{
		if(!is_dir($local_dirname)){
			mkdir($local_dirname);
		}

		$files = $this->connection->rawlist();
		if(!$files[$remote_dirname]["type"] === 2) 
			throw new \LogicException(sprintf("%s is not a directory"), $remote_dirname);
		
		$filesToDownload = array_splice($this->connection->rawlist($remote_dirname), 2);
		
		foreach($filesToDownload as $filename => $fileinfos) {
			if($fileinfos["type"] === 1) {
				if(!file_exists("$local_dirname/$filename")) {
					$this->connection->get("$remote_dirname/$filename", "$local_dirname/$filename");
				}
			} else {
				$this->get_dir("$remote_dirname/$filename", "$local_dirname/$filename");
			}
		}
		return true;
	}

	public function get(string $remote_file, string $local_file): string
	{
		$this->connection->get($remote_file, $local_file);
		return $remote_file;
	}

	private function recursive_delete(string $filename, string $currentPosition)
	{
		$files = array_slice($this->connection->rawlist($currentPosition), 2);
		//if file does not exist, return false
		if(!array_key_exists($filename, $files)) {
			return false;
		}
		//if file is a folder, make a recursive delete
		if($files[$filename]["type"] === 2) {
			$subFiles = array_slice($this->connection->rawlist("$currentPosition/$filename"), 2);
			foreach($subFiles as $subfilename => $subfileinfos) {
				$this->recursive_delete($subfilename, "$currentPosition/$filename");
			}

			return $this->connection->rmdir("$currentPosition/$filename");
		} else {
			return $this->connection->delete("$currentPosition/$filename");
		}
	}
	
	public function current_dir()
	{
		return $this->connection->pwd();
	}

	public function ls(bool $raw = true, ?string $order = null, ?string $valueOrdered = "filename")
	{
		$orders = ["ASC" => SORT_ASC, "DESC" => SORT_DESC];
		if($order) {
			if(!in_array($order, ["ASC", "DESC"])) {
				throw new \LogicException(sprintf("Unknow order operator. Must be ASC or DESC"));
			}
			if(!in_array($valueOrdered, ["filename", "size", "atime", "mtime"])) {
				throw new \LogicException(sprintf("Unknow order operator. Must be in (filename, size, atime, mtime)"));
			}
			$this->connection->setListOrder($valueOrdered, $orders[$order]);
		} else {
			$this->connection->setListOrder();
		}
		$list = $this->connection->nlist();

		if($raw) 
			$list = array_slice($list, 2);
		
		return $list;
	}
}
