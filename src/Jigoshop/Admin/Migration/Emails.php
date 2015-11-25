<?php

namespace Jigoshop\Admin\Migration;

use Jigoshop\Entity\Product;
use Jigoshop\Helper\Render;
use WPAL\Wordpress;

class Emails implements Tool
{
	const ID = 'jigoshop_emails_migration';

	/** @var Wordpress */
	private $wp;
	/** @var \Jigoshop\Core\Options */
	private $options;

	public function __construct(Wordpress $wp, \Jigoshop\Core\Options $options)
	{
		$this->wp = $wp;
		$this->options = $options;
		$wp->addAction('wp_ajax_jigoshop.admin.migration.emails', array($this, 'ajaxMigrationEmails'), 10, 0);

	}

	/**
	 * @return string Tool ID.
	 */
	public function getId()
	{
		return self::ID;
	}

	/**
	 * Shows migration tool in Migration tab.
	 */
	public function display()
	{
		$wpdb = $this->wp->getWPDB();

		$countAll = count($wpdb->get_results($wpdb->prepare("
			SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type IN (%s) AND p.post_status <> %s
			GROUP BY p.ID",
			array('shop_email', 'auto-draft'))));

		$countRemain = 0;

		if (($itemsFromBase = $this->wp->getOption('jigoshop_emails_migrate_id')) !== false)
		{
			$countRemain = count(unserialize($itemsFromBase));
		}

		Render::output('admin/migration/emails', array('countAll' => $countAll, 'countDone' => ($countAll - $countRemain)));
	}

	/**
	 * Migrates data from old format to new one.
	 * @param mixed $emails
	 * @return bool migration email status: success or not
	 */
	public function migrate($emails)
	{
		$wpdb = $this->wp->getWPDB();

//		Open transaction for save migration emails
		$var_autocommit_sql = $wpdb->get_var("SELECT @@AUTOCOMMIT");
		try
		{
			$wpdb->query("SET AUTOCOMMIT=0");
			$this->checkSql();
			$wpdb->query("START TRANSACTION");
			$this->checkSql();

			for ($i = 0, $endI = count($emails); $i < $endI;) {
				$email = $emails[$i];

				// Update columns
				do {
					$key = $this->_transformKey($emails[$i]->meta_key);

					if ($key !== null) {
						$wpdb->query($wpdb->prepare(
							"UPDATE {$wpdb->postmeta} SET meta_value = %s, meta_key = %s WHERE meta_id = %d;",
							array(
								$this->_transform($emails[$i]->meta_key, $emails[$i]->meta_value),
								$key,
								$emails[$i]->meta_id,
							)
						));
						$this->checkSql();
					}
					$i++;
				} while ($i < $endI && $emails[$i]->ID == $email->ID);
			}

//			commit sql transation and restore value of autocommit
			$wpdb->query("COMMIT");
			$wpdb->query("SET AUTOCOMMIT=" . $var_autocommit_sql);
			return true;

		} catch (Exception $e)
		{
//          rollback sql transation and restore value of autocommit
			if(WP_DEBUG)
			{
				\Monolog\Registry::getInstance(JIGOSHOP_LOGGER)->addDebug($e);
			}
			$wpdb->query("ROLLBACK");
			$wpdb->query("SET AUTOCOMMIT=" . $var_autocommit_sql);
			return false;
		}
	}

	private function _transform($key, $value)
	{
		switch ($key) {
			case 'jigoshop_email_actions':
				$value = unserialize($value);

				return serialize(array_map(function ($item){
					switch ($item) {
						case 'admin_order_status_pending_to_on-hold':
							return 'admin_order_status_pending_to_on_hold';
						case 'customer_order_status_pending_to_on-hold':
							return 'customer_order_status_pending_to_on_hold';
						case 'customer_order_status_on-hold_to_processing':
							return 'customer_order_status_on_hold_to_processing';
						case 'product_on_backorder_notification':
							return 'product_on_backorders_notification';
						default:
							return $item;
					}
				}, $value));
			default:
				return $value;
		}
	}

	private function _transformKey($key)
	{
		switch ($key) {
			case 'jigoshop_email_subject':
				return 'subject';
			case 'jigoshop_email_actions':
				return 'actions';
			default:
				return null;
		}
	}

	public function ajaxMigrationEmails()
	{
		try {
			$wpdb = $this->wp->getWPDB();

			$query = $wpdb->prepare("
				SELECT DISTINCT p.ID, pm.* FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
					WHERE p.post_type IN (%s) AND p.post_status <> %s",
				array('shop_email', 'auto-draft'));
			$emails = $wpdb->get_results($query);

			$joinEmails = array();
			$emailsIdsMigration = array();

			for ($aa = 0; $aa < count($emails); $aa++)
			{
				$joinEmails[$emails[$aa]->ID][$emails[$aa]->meta_id] = new \stdClass();
				foreach ($emails[$aa] as $k => $v)
				{
					$joinEmails[$emails[$aa]->ID][$emails[$aa]->meta_id]->$k = $v;
					$emailsIdsMigration[] = $emails[$aa]->ID;
				}
			}

			$emailsIdsMigration = array_unique($emailsIdsMigration);
			$countAll = count($emailsIdsMigration);

			//TODO usunac
			if(isset($_POST['wwee']))
			{
				$this->wp->updateOption('jigoshop_emails_migrate_id', serialize($emailsIdsMigration));
				echo json_encode(array(
					'success' => true,
				));
				exit;
			}

			if (($TMP_emailsIdsMigration = $this->wp->getOption('jigoshop_emails_migrate_id')) !== false)
			{
				$emailsIdsMigration = unserialize($TMP_emailsIdsMigration);
			}

			$singleEmailsId = array_shift($emailsIdsMigration);
			$countRemain = count($emailsIdsMigration);

			sort($joinEmails[$singleEmailsId]);

			if ($this->migrate($joinEmails[$singleEmailsId]))
			{
				$this->wp->updateOption('jigoshop_emails_migrate_id', serialize($emailsIdsMigration));
				echo json_encode(array(
					'success' => true,
					'percent' => floor(($countAll - $countRemain) / $countAll * 100),
					'processed' => $countAll - $countRemain,
					'remain' => $countRemain,
					'total' => $countAll,
				));
			}
			else
			{
				echo json_encode(array(
					'success' => false,
				));
			}

		} catch (Exception $e) {
			if(WP_DEBUG)
			{
				\Monolog\Registry::getInstance(JIGOSHOP_LOGGER)->addDebug($e);
			}
			echo json_encode(array(
				'success' => false,
			));
		}

		exit;
	}
}
