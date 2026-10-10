-- Finish the u502532383_SGN22.sql import after it stopped at the
-- `site_launch_tokens` constraints (#1452).
-- Run once in phpMyAdmin on the NEW database (SQL tab).

-- 1. Three old launch tokens point at site integration #1, which no longer exists.
--    Clear the dead links (the constraint itself uses ON DELETE SET NULL).
UPDATE `site_launch_tokens`
SET `site_integration_id` = NULL
WHERE `site_integration_id` IS NOT NULL
  AND `site_integration_id` NOT IN (SELECT `id` FROM `site_integrations`);

UPDATE `site_launch_tokens`
SET `user_tool_id` = NULL
WHERE `user_tool_id` IS NOT NULL
  AND `user_tool_id` NOT IN (SELECT `id` FROM `user_tools`);

UPDATE `site_launch_tokens`
SET `hub_user_id` = NULL
WHERE `hub_user_id` IS NOT NULL
  AND `hub_user_id` NOT IN (SELECT `id` FROM `users`);

-- Integration rows for tools #1-#5, which were deleted on the old server.
DELETE FROM `user_tool_integrations`
WHERE `user_tool_id` NOT IN (SELECT `id` FROM `user_tools`);

-- 2. Every constraint from the failed statement to the end of the dump
--    (phpMyAdmin stops at the first error, so none of these were applied).

ALTER TABLE `site_launch_tokens`
  ADD CONSTRAINT `site_launch_tokens_hub_user_id_foreign` FOREIGN KEY (`hub_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `site_launch_tokens_site_integration_id_foreign` FOREIGN KEY (`site_integration_id`) REFERENCES `site_integrations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `site_launch_tokens_user_tool_id_foreign` FOREIGN KEY (`user_tool_id`) REFERENCES `user_tools` (`id`) ON DELETE SET NULL;

ALTER TABLE `support_attachments`
  ADD CONSTRAINT `support_attachments_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_attachments_support_ticket_reply_id_foreign` FOREIGN KEY (`support_ticket_reply_id`) REFERENCES `support_ticket_replies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_attachments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `support_ticket_replies`
  ADD CONSTRAINT `support_ticket_replies_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_ticket_replies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `users_referred_by_id_foreign` FOREIGN KEY (`referred_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_suspended_by_foreign` FOREIGN KEY (`suspended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `user_activity`
  ADD CONSTRAINT `user_activity_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_auth_providers`
  ADD CONSTRAINT `user_auth_providers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_bank_accounts`
  ADD CONSTRAINT `user_bank_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_tools`
  ADD CONSTRAINT `user_tools_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_tools_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_tools_platform_product_id_foreign` FOREIGN KEY (`platform_product_id`) REFERENCES `platform_products` (`id`),
  ADD CONSTRAINT `user_tools_platform_product_variant_id_foreign` FOREIGN KEY (`platform_product_variant_id`) REFERENCES `platform_product_variants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_tools_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_tool_integrations`
  ADD CONSTRAINT `user_tool_integrations_user_tool_id_foreign` FOREIGN KEY (`user_tool_id`) REFERENCES `user_tools` (`id`) ON DELETE CASCADE;

ALTER TABLE `wallets`
  ADD CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `wallet_fundings`
  ADD CONSTRAINT `wallet_fundings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wallet_fundings_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE;

ALTER TABLE `wallet_holds`
  ADD CONSTRAINT `wallet_holds_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE;

ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_user_bank_account_id_foreign` FOREIGN KEY (`user_bank_account_id`) REFERENCES `user_bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `withdrawals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawals_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE;
