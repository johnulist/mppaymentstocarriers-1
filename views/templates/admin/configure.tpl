{*
 * 2016 Mijn Presta
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@mijnpresta.nl so we can send you a copy immediately.
 *
 *  @author    Michael Dekker <info@mijnpresta.nl>
 *  @copyright 2016 Mijn Presta
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<div class="panel">
	<h3><i class="icon icon-rocket"></i> {l s='Link carriers to payment methods' mod='mppaymentstocarriers'}</h3>
	<p>
		<strong>{l s='Link carriers to payment methods' mod='mppaymentstocarriers'}</strong><br />
		{l s='This module allows you to hide payment methods when a certain carrier is selected. You can use this to charge an additional fee when the custom for example chooses cash on delivery. On the configuration page "Modules and Services > Payment" you will see a new section where you can configure the restrictions (Carrier restrictions).' mod='mppaymentstocarriers'}
	</p>

	<strong>{l s='Quick start' mod='mppaymentstocarriers'}</strong><br />
	{l s='Check if the following settings are correct:' mod='mppaymentstocarriers'}
	<ol>
		<li>
			{l s='The module has been hooked onto:' mod='mppaymentstocarriers'}
			<ul>
				<li><kbd>actionObjectCarrierDeleteBefore</kbd></li>
			</ul>
		</li>
		<li>
			{l s='The carriers have been configured on the page "%s > %s".' mod='mppaymentstocarriers' sprintf=[$modulesServices, $payment]}
		</li>
	</ol>
	<p>{l s='You are good to go! Should you find any problems, please contact us through PrestaShop Addons.' mod='mppaymentstocarriers'}</p>
</div>

<div class="panel">
	<h3><i class="icon icon-tags"></i> {l s='Documentation' mod='mppaymentstocarriers'}</h3>
	<p>
		&raquo; {l s='Documentation for this module is available' mod='mppaymentstocarriers'}:
	<ul>
		<li><a href="/modules/mppaymentstocarriers/docs/readme_en.pdf" target="_blank">{l s='English' mod='mppaymentstocarriers'}</a></li>
	</ul>
	</p>
</div>

<div class="panel">
	<h3><i class="icon icon-warning"></i> {l s='Troubleshooter' mod='mppaymentstocarriers'}</h3>
	{include file='./troubleshooter_info.tpl' module_errors=$module_errors module_warnings=$module_warnings module_confirmations=$module_confirmations}
	<a href="{$current_page|escape:'htmlall':'UTF-8'}&fixitall=1" class="btn btn-default">{l s='Attempt to fix it all!' mod='mppaymentstocarriers'}</a>
</div>

<div class="panel">
	<h3><i class="icon icon-truck"></i> {l s='Carrier restrictions' mod='mppaymentstocarriers'}</h3>
	<div class="alert alert-info">{l s='You can configure your carrier restrictions on' mod='mppaymentstocarriers'} <a href="{$paymentPage|escape:'htmlall':'UTF-8'}">{l s='on this page' mod='mppaymentstocarriers'}</a>.</div>
</div>
