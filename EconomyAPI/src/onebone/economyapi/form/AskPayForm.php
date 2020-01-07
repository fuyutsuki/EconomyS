<?php

/*
 * EconomyS, the massive economy plugin with many features for PocketMine-MP
 * Copyright (C) 2013-2020  onebone <me@onebone.me>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace onebone\economyapi\form;

use onebone\economyapi\EconomyAPI;
use onebone\economyapi\event\CommandIssuer;
use onebone\economyapi\event\money\PayMoneyEvent;
use onebone\economyapi\util\Transaction;
use onebone\economyapi\util\TransactionAction;
use pocketmine\form\Form;
use pocketmine\Player;

class AskPayForm implements Form {
	/** @var EconomyAPI */
	private $plugin;
	/** @var Player */
	private $player;
	/** @var string */
	private $target;
	/** @var float */
	private $amount;

	private $label;
	private $params;

	public function __construct(EconomyAPI $plugin, Player $player, string $target, float $amount, $label, $params) {
		$this->plugin = $plugin;
		$this->player = $player;
		$this->target = $target;
		$this->amount = $amount;

		$this->label = $label;
		$this->params = $params;
	}

	public function handleResponse(Player $player, $data): void {
		if(!is_bool($data)) {
			$player->sendMessage($this->plugin->getMessage("pay-failed", [], $player->getName()));
			return;
		}

		if(!$data) {
			$player->sendMessage($this->plugin->getMessage("pay-cancelled", [], $player->getName()));
			return;
		}

		$ev = new PayMoneyEvent($this->plugin, $this->player->getName(), $this->target, $this->amount,
			new CommandIssuer($this->player, $this->label, $this->label . ' ' . implode(' ', $this->params)));
		$ev->call();

		if($ev->isCancelled()) {
			$player->sendMessage($this->plugin->getMessage("pay-failed", [
				$this->target,
				$this->amount
			], $player->getName()));
			return;
		}

		if($this->plugin->executeTransaction(new Transaction([
			new TransactionAction(Transaction::ACTION_REDUCE, $player, $this->amount),
			new TransactionAction(Transaction::ACTION_ADD, $this->target, $this->amount)
		]))) {
			$player->sendMessage($this->plugin->getMessage("pay-success", [
				$this->amount,
				$this->target
			], $player->getName()));

			$p = $this->plugin->getServer()->getPlayerExact($this->target);
			if($p instanceof Player) {
				$p->sendMessage($this->plugin->getMessage("money-paid", [
					$player->getName(),
					$this->amount
				], $this->target));
			}
		}else{
			$player->sendMessage($this->plugin->getMessage("pay-failed", [
				$this->target,
				$this->amount
			], $player->getName()));
		}
	}

	public function jsonSerialize() {
		return [
			'type' => 'modal',
			'title' => $this->plugin->getMessage("pay-ask-title", [], $this->player->getName()),
			'content' => $this->plugin->getMessage("pay-ask-content", [
				$this->target, $this->amount
			], $this->player->getName()),
			'button1' => $this->plugin->getMessage("pay-confirm", [], $this->player->getName()),
			'button2' => $this->plugin->getMessage("pay-cancel", [], $this->player->getName())
		];
	}
}