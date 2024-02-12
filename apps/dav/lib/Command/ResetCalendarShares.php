<?php
/**
 * @copyright Copyright (c) 2016 Thomas Citharel <nextcloud@tcit.fr>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\DAV\Command;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ResetCalendarShares extends Command {
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private IShareManager $shareManager;
	private IConfig $config;
	private IL10N $l10n;
	private ?SymfonyStyle $io = null;
	private CalDavBackend $calDav;
	private LoggerInterface $logger;

	public const URI_USERS = 'principals/users/';

	public function __construct(
		IUserManager $userManager,
		IGroupManager $groupManager,
		IShareManager $shareManager,
		IConfig $config,
		IL10N $l10n,
		CalDavBackend $calDav,
		LoggerInterface $logger
	) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->shareManager = $shareManager;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->calDav = $calDav;
		$this->logger = $logger;
	}

	protected function configure() {
		$this
			->setName('dav:reset-calendar-shares')
			->setDescription('reset calendar shares')
			->addArgument('uid',
				InputArgument::REQUIRED,
				'User')
			->addArgument('calname',
				InputArgument::REQUIRED,
				'Calendar');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$user = $input->getArgument('uid');

		$this->io = new SymfonyStyle($input, $output);

		if (!$this->userManager->userExists($user)) {
			throw new \InvalidArgumentException("User <$user> is unknown.");
		}

		$calname = $input->getArgument('calname');

		$calendar = $this->calDav->getCalendarByUri(self::URI_USERS . $user, $calname);

		if (null === $calendar) {
			throw new \InvalidArgumentException("User <$user> has no calendar named <$calname>. You can run occ dav:list-calendars to list calendars URIs for this user.");
		}

		$shares = $this->calDav->getShares($calendar['id']);
		foreach ($shares as $share) {
			if ($share['href'] != $userhref) {
				$output->writeln("<info>Updating <$calname> to remove shariing with <${share['commonName']}></info>");
				$this->calDav->updateShares(new Calendar($this->calDav, $calendar, $this->l10n, $this->config, $this->logger), [], [$share['href']]);
			}
		}

		return 0;
	}

	/**
	 * Check if the calendar exists for user
	 *
	 * @param string $userDestination
	 * @param string $name
	 * @return bool
	 */
	protected function calendarExists(string $userDestination, string $name): bool {
		return null !== $this->calDav->getCalendarByUri(self::URI_USERS . $userDestination, $name);
	}

}
