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
{if !empty($module_confirmations)}
	<div class="bootstrap">
		<div class="module_confirmation conf confirm alert alert-success">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			{if count($module_confirmations) > 1}
				<ul>
				{foreach from=$module_confirmations item=confirmation}
					<li>{$confirmation|escape:'htmlall':'UTF-8'}</li>
				{/foreach}
				</ul>
			{elseif count($module_confirmations) == 1}
				{$module_confirmations[0]|escape:'htmlall':'UTF-8'}
			{/if}
		</div>
	</div>
{/if}

{if !empty($module_warnings)}
	<div class="bootstrap">
		<div class="module_warning alert alert-warning">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			{if count($module_warnings) > 1}
				<ul>
					{foreach from=$module_warnings item=warning}
						<li>{$warning|escape:'htmlall':'UTF-8'}</li>
					{/foreach}
				</ul>
			{elseif count($module_warnings) == 1}
				{$module_warnings[0]|escape:'htmlall':'UTF-8'}
			{/if}
		</div>
	</div>
{/if}

{if !empty($module_errors)}
	<div class="bootstrap">
		<div class="module_error alert alert-danger">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			{if count($module_errors) > 1}
				<ul>
					{foreach from=$module_errors item=error}
						<li>{$error|escape:'htmlall':'UTF-8'}</li>
					{/foreach}
				</ul>
			{elseif count($module_errors) == 1}
				{$module_errors[0]|escape:'htmlall':'UTF-8'}
			{/if}
		</div>
	</div>
{/if}


