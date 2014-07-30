<?php
/**
 * ownCloud - Mail app
 *
 * @author Thomas Müller
 * @copyright 2013 Thomas Müller thomas.mueller@tmit.eu
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Mail\Controller;

use OCA\Mail\Db\MailAccount;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http;

class FoldersController extends Controller
{

	/**
	 * @var \OCA\Mail\Db\MailAccountMapper
	 */
	private $mapper;

	/**
	 * @var string
	 */
	private $currentUserId;

	public function __construct($appName, $request, $mailAccountMapper, $currentUserId){
		parent::__construct($appName, $request);
		$this->mapper = $mailAccountMapper;
		$this->currentUserId = $currentUserId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$accounts = $this->getAccount();
		$json = array();

		foreach($accounts as $account) {
			$m = new \OCA\Mail\Account($account);
			$json[$account->getMailAccountId()] = $m->getListArray();
		}

		return new JSONResponse($json);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function show() {
		$response = new JSONResponse();
		$response->setStatus(Http::STATUS_NOT_IMPLEMENTED);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function update() {
		$response = new JSONResponse();
		$response->setStatus(Http::STATUS_NOT_IMPLEMENTED);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy($folderId) {
		try {
			$account = $this->getAccount();
			$imap = $this->getImap($account);
			$imap->deleteMailbox($folderId);

			return new JSONResponse();
		} catch (DoesNotExistException $e) {
			return new JSONResponse();
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create() {
		try {
			$mailbox = $this->params('mailbox');
			$account = $this->getAccount();
			$imap = $this->getImap($account);

			// TODO: read http://tools.ietf.org/html/rfc6154
			$imap->createMailbox($mailbox);

			$newFolderId = $mailbox;
			return new JSONResponse(
				array('data' => array('id' => $newFolderId)),
				Http::STATUS_CREATED);
		} catch (\Horde_Imap_Client_Exception $e) {
			$response = new JSONResponse();
			$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			return $response;
		} catch (DoesNotExistException $e) {
			return new JSONResponse();
		}
	}

	/**
	 * TODO: private functions below have to be removed from controller -> imap service to be build
	 */

	/**
	 * @param string $host
	 * @param int $port
	 * @param string $user
	 * @param string $password
	 * @param string $ssl_mode
	 * @return \Horde_Imap_Client_Socket a ready to use IMAP connection
	 */
	private function getImapConnection($host, $port, $user, $password, $ssl_mode) {
		$imapConnection = new \Horde_Imap_Client_Socket(array(
			'username' => $user,
			'password' => $password,
			'hostspec' => $host,
			'port' => $port,
			'secure' => $ssl_mode,
			'timeout' => 2));

		$imapConnection->login();
		return $imapConnection;
	}

	/**
	 * @return array|\OCA\Mail\Db\MailAccount[]
	 */
	private function getAccount()
	{
		$accountId = $this->params('accountId');

		if($accountId === 'allAccounts') {
			return $this->mapper->findByUserId($this->currentUserId);
		}

		return array($this->mapper->find($this->currentUserId, $accountId));
	}

	private function getImap(MailAccount $account)
	{
		return $this->getImapConnection(
			$account->getInboundHost(),
			$account->getInboundHostPort(),
			$account->getInboundUser(),
			$account->getInboundPassword(),
			$account->getInboundSslMode()
		);
	}
}