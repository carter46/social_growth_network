/**
 * Clean u502532383_tradehub.sql for Social Growth Network import.
 * Keeps users/admins/wallets/platform orders; removes marketplace/crypto tables
 * and non-social catalog rows.
 */
const fs = require('fs');
const path = require('path');
const readline = require('readline');

const INPUT = path.join(__dirname, 'u502532383_tradehub.sql');
const OUTPUT = path.join(__dirname, 'u502532383_tradehub_cleaned.sql');

const DROP_TABLES = new Set([
  'categories',
  'crypto_deposit_wallets',
  'crypto_sell_requests',
  'escrows',
  'exchange_rates',
  'exchange_rate_history',
  'incoming_crypto_transactions',
  'listings',
  'listing_versions',
  'marketplace_products',
  'messages',
  'otc_pricing_settings',
  'product_reviews',
  'reviews',
  'wallet_balance_history',
  'watchlists',
]);

/** Keep table structure, strip all rows (orphaned FKs to removed products). */
const EMPTY_DATA_TABLES = new Set([
  'domain_quotes',
  'domain_registrations',
  'domain_connections',
]);

const KEEP_PRODUCT_TYPES = new Set(['social_service']);
const KEEP_SERVICE_CATEGORY_SLUGS = new Set([
  'social-media',
  'youtube',
  'facebook',
  'instagram',
  'tiktok',
  'twitter',
]);
const KEEP_PRODUCT_TYPE_SLUGS = new Set(['social_service']);

const SOCIAL_SLUGS = new Set([
  // Canonical (post-rename)
  'youtube-views',
  'youtube-likes',
  'youtube-comments',
  'youtube-watch-hours',
  'youtube-subscribers',
  'facebook-views',
  'facebook-likes',
  'facebook-comments',
  'instagram-views',
  'instagram-likes',
  'instagram-comments',
  'tiktok-views',
  'tiktok-likes',
  'tiktok-comments',
  'twitter-views',
  'twitter-likes',
  'twitter-comments',
  // Legacy Views slugs (kept so dump import can be renamed by seeder)
  'youtube-views-lite',
  'facebook-growth-pack',
  'instagram-growth-pack',
  'tiktok-engagement-boost',
  'twitter-audience-pack',
]);

function tableFromHeader(line) {
  const m = line.match(/table `([^`]+)`/i);
  return m ? m[1] : null;
}

function tableFromAlter(line) {
  const m = line.match(/^ALTER TABLE `([^`]+)`/i);
  return m ? m[1] : null;
}

function tableFromInsert(line) {
  const m = line.match(/^INSERT INTO `([^`]+)`/i);
  return m ? m[1] : null;
}

/** Split MySQL VALUES rows respecting quotes/escapes. */
function splitValueRows(valuesSql) {
  const rows = [];
  let depth = 0;
  let inStr = false;
  let esc = false;
  let start = -1;
  for (let i = 0; i < valuesSql.length; i++) {
    const ch = valuesSql[i];
    if (inStr) {
      if (esc) {
        esc = false;
      } else if (ch === '\\') {
        esc = true;
      } else if (ch === "'") {
        // MySQL '' escape inside strings
        if (valuesSql[i + 1] === "'") {
          i++;
        } else {
          inStr = false;
        }
      }
      continue;
    }
    if (ch === "'") {
      inStr = true;
      continue;
    }
    if (ch === '(') {
      if (depth === 0) start = i;
      depth++;
      continue;
    }
    if (ch === ')') {
      depth--;
      if (depth === 0 && start >= 0) {
        rows.push(valuesSql.slice(start, i + 1));
        start = -1;
      }
    }
  }
  return rows;
}

function parseSimpleFields(row) {
  // row like (1, 'a', NULL, ...)
  const inner = row.slice(1, -1);
  const fields = [];
  let inStr = false;
  let esc = false;
  let cur = '';
  for (let i = 0; i < inner.length; i++) {
    const ch = inner[i];
    if (inStr) {
      cur += ch;
      if (esc) {
        esc = false;
      } else if (ch === '\\') {
        esc = true;
      } else if (ch === "'") {
        if (inner[i + 1] === "'") {
          cur += "'";
          i++;
        } else {
          inStr = false;
        }
      }
      continue;
    }
    if (ch === "'") {
      inStr = true;
      cur += ch;
      continue;
    }
    if (ch === ',' && !inStr) {
      fields.push(cur.trim());
      cur = '';
      continue;
    }
    cur += ch;
  }
  if (cur.trim() !== '' || fields.length) fields.push(cur.trim());
  return fields;
}

function unquote(field) {
  if (field === 'NULL') return null;
  if (field.startsWith("'") && field.endsWith("'")) {
    return field.slice(1, -1).replace(/''/g, "'").replace(/\\'/g, "'");
  }
  return field;
}

function filterInsert(table, statement) {
  const valuesIdx = statement.search(/\bVALUES\b/i);
  if (valuesIdx < 0) return statement;
  const head = statement.slice(0, valuesIdx + 6);
  const tail = statement.slice(valuesIdx + 6).replace(/;\s*$/, '');
  const rows = splitValueRows(tail);
  if (!rows.length) return null;

  let kept = rows;

  if (table === 'orders') {
    kept = rows.filter((r) => {
      const f = parseSimpleFields(r);
      // source is 2nd column (index 1)
      return unquote(f[1]) !== 'marketplace';
    });
  } else if (table === 'platform_products') {
    kept = rows.filter((r) => {
      const f = parseSimpleFields(r);
      // product_type is 3rd column (index 2)
      const type = unquote(f[2]);
      const slug = unquote(f[4]);
      return KEEP_PRODUCT_TYPES.has(type) || SOCIAL_SLUGS.has(slug);
    });
  } else if (table === 'product_types') {
    kept = rows.filter((r) => {
      const f = parseSimpleFields(r);
      // slug typically column 2 or look for social_service
      return f.some((x) => unquote(x) === 'social_service');
    });
  } else if (table === 'service_categories') {
    kept = rows.filter((r) => {
      const f = parseSimpleFields(r);
      return f.some((x) => unquote(x) === 'social-media' || unquote(x) === 'social');
    });
  } else if (table === 'wallet_holds') {
    kept = rows.filter((r) => {
      const f = parseSimpleFields(r);
      const joined = f.map(unquote).join('|');
      return !/escrow|listing/i.test(joined);
    });
  } else if (table === 'transactions') {
    kept = rows.filter((r) => {
      const f = parseSimpleFields(r);
      const types = f.map(unquote);
      return !types.includes('escrow_lock')
        && !types.includes('escrow_release')
        && !types.includes('listing_hold')
        && !types.includes('listing_hold_release');
    });
  } else if (table === 'catalog_page_contents') {
    kept = rows.filter((r) => {
      const f = parseSimpleFields(r);
      const text = f.map(unquote).join(' ');
      if (/network-services|communication|website-services|business-documents|trust-escrow|vpn|proxy|smtp|domain|receipt|document|virtual_phone|email|website_package/i.test(text)
        && !/social/i.test(text)) {
        return false;
      }
      return true;
    });
  }

  if (!kept.length) return null;
  return head + '\n' + kept.join(',\n') + ';\n';
}

async function main() {
  const stats = {
    droppedTables: 0,
    keptLines: 0,
    skippedLines: 0,
    filteredInserts: 0,
    emptiedInserts: 0,
  };

  const out = fs.createWriteStream(OUTPUT, { encoding: 'utf8' });
  out.write('-- Cleaned dump for Social Growth Network\n');
  out.write('-- Source: u502532383_tradehub.sql\n');
  out.write('-- Removed marketplace/crypto tables; kept users/admins/wallets; trimmed non-social catalog.\n');
  out.write('-- Emptied domain_quotes/registrations/connections; kept only social platform orders/tools.\n');
  out.write('-- After import: php artisan migrate --force\n');
  out.write('-- If social products missing: php artisan db:seed --class=Database\\\\Seeders\\\\ProductionSeeder --force\n\n');
  out.write('SET FOREIGN_KEY_CHECKS=0;\n');

  const rl = readline.createInterface({
    input: fs.createReadStream(INPUT, { encoding: 'utf8' }),
    crlfDelay: Infinity,
  });

  let currentTable = null;
  let skipping = false;
  let insertBuf = null;
  let insertTable = null;
  let socialProductIds = new Set();

  // First pass collect social product ids from a quick scan
  const full = fs.readFileSync(INPUT, 'utf8');
  {
    const re = /INSERT INTO `platform_products`[\s\S]*?;/g;
    let m;
    while ((m = re.exec(full))) {
      const rows = splitValueRows(m[0].slice(m[0].search(/\bVALUES\b/i) + 6));
      for (const r of rows) {
        const f = parseSimpleFields(r);
        const id = unquote(f[0]);
        const type = unquote(f[2]);
        const slug = unquote(f[4]);
        if (KEEP_PRODUCT_TYPES.has(type) || SOCIAL_SLUGS.has(slug)) {
          socialProductIds.add(String(id));
        }
      }
    }
    console.log('Social product IDs:', [...socialProductIds].join(', ') || '(none)');
  }

  function flushInsert() {
    if (!insertBuf) return;
    const stmt = insertBuf;
    insertBuf = null;
    const table = insertTable;
    insertTable = null;

    if (DROP_TABLES.has(table) || EMPTY_DATA_TABLES.has(table)) {
      stats.emptiedInserts++;
      return;
    }

    let filtered = filterInsert(table, stmt);

    // Second-level filter for variants/images by product id
    if (filtered && (table === 'platform_product_variants' || table === 'platform_product_images')) {
      const valuesIdx = filtered.search(/\bVALUES\b/i);
      const head = filtered.slice(0, valuesIdx + 6);
      const rows = splitValueRows(filtered.slice(valuesIdx + 6).replace(/;\s*$/, ''));
      const kept = rows.filter((r) => {
        const f = parseSimpleFields(r);
        // platform_product_id is usually column index 1
        const pid = unquote(f[1]);
        return socialProductIds.has(String(pid));
      });
      filtered = kept.length ? head + '\n' + kept.join(',\n') + ';\n' : null;
    }

    // order_items: drop rows for marketplace orders — harder without order id map;
    // keep all order_items for remaining platform orders only by scanning order ids kept.
    // Handled below via marketplaceOrderIds if we build it — for simplicity keep order_items
    // that reference remaining orders; migrate already deleted marketplace orders in 000200
    // but if those orders aren't imported, orphan items may remain. Filter by collecting
    // kept order ids during orders filter — do a post note. Optional: leave order_items as-is
    // for platform-only dump after orders filtered; orphaned marketplace items if orders gone
    // could FK fail. So we must filter order_items too.

    if (filtered) {
      out.write(filtered);
      if (filtered !== stmt) stats.filteredInserts++;
      stats.keptLines++;
    } else {
      stats.emptiedInserts++;
    }
  }

  // Collect kept order ids while filtering
  const keptOrderIds = new Set();
  const originalFilterInsert = filterInsert;
  filterInsert = function (table, statement) {
    const result = originalFilterInsert(table, statement);
    if (table === 'orders' && result) {
      const valuesIdx = result.search(/\bVALUES\b/i);
      const rows = splitValueRows(result.slice(valuesIdx + 6).replace(/;\s*$/, ''));
      for (const r of rows) {
        const id = unquote(parseSimpleFields(r)[0]);
        if (id) keptOrderIds.add(String(id));
      }
    }
    return result;
  };

  for await (const line of rl) {
    // Detect section headers
    if (/^-- Table structure for table/.test(line) || /^-- Dumping data for table/.test(line)
      || /^-- Indexes for table/.test(line) || /^-- AUTO_INCREMENT for table/.test(line)
      || /^-- Constraints for table/.test(line)) {
      flushInsert();
      currentTable = tableFromHeader(line);
      skipping = DROP_TABLES.has(currentTable);
      if (skipping) {
        stats.droppedTables++;
        stats.skippedLines++;
        continue;
      }
      out.write(line + '\n');
      stats.keptLines++;
      continue;
    }

    if (skipping) {
      // Stop skipping when we hit next non-related content for another table
      // Keep skipping until new header changes table — headers handled above.
      // Also skip ALTER for dropped tables even outside skip mode
      stats.skippedLines++;
      continue;
    }

    const alterTable = tableFromAlter(line);
    if (alterTable && DROP_TABLES.has(alterTable)) {
      flushInsert();
      // Skip this ALTER and continuation lines until semicolon
      skipping = true;
      // Use a one-shot alter skip via buffer
      let alterBuf = line;
      if (line.includes(';')) {
        skipping = false;
        currentTable = null;
        stats.skippedLines++;
        continue;
      }
      // Continue reading in loop — set flag alterSkip
      // Simpler: set currentTable to alterTable and skipping true until line with ;
      currentTable = alterTable;
      stats.skippedLines++;
      continue;
    }

    if (skipping && DROP_TABLES.has(currentTable)) {
      stats.skippedLines++;
      if (line.includes(';')) {
        skipping = false;
        currentTable = null;
      }
      continue;
    }

    const insTable = tableFromInsert(line);
    if (insTable) {
      flushInsert();
      if (DROP_TABLES.has(insTable)) {
        insertBuf = line + '\n';
        insertTable = insTable;
        if (line.trim().endsWith(';')) {
          flushInsert();
        }
        continue;
      }
      insertBuf = line + '\n';
      insertTable = insTable;
      if (line.trim().endsWith(';')) {
        flushInsert();
      }
      continue;
    }

    if (insertBuf) {
      insertBuf += line + '\n';
      if (line.trim().endsWith(';')) {
        flushInsert();
      }
      continue;
    }

    // CREATE TABLE for dropped — if we somehow aren't in skip mode
    const createMatch = line.match(/^CREATE TABLE `([^`]+)`/);
    if (createMatch && DROP_TABLES.has(createMatch[1])) {
      skipping = true;
      currentTable = createMatch[1];
      stats.skippedLines++;
      continue;
    }

    out.write(line + '\n');
    stats.keptLines++;
  }

  flushInsert();
  out.write('\nSET FOREIGN_KEY_CHECKS=1;\n');
  out.end();
  await new Promise((r) => out.on('finish', r));

  console.log('Social product IDs for post-filter:', [...socialProductIds].join(', '));
  postFilterOrphanRows(OUTPUT, socialProductIds);

  console.log('Done:', OUTPUT);
  console.log(stats);
  console.log('Output size MB:', (fs.statSync(OUTPUT).size / 1e6).toFixed(2));
}

function rewriteInsert(block, keepRow) {
  const valuesIdx = block.search(/\bVALUES\b/i);
  if (valuesIdx < 0) return block;
  const head = block.slice(0, valuesIdx + 6);
  const rows = splitValueRows(block.slice(valuesIdx + 6).replace(/;\s*$/, ''));
  const kept = rows.filter(keepRow);
  if (!kept.length) return `-- emptied insert (FK-safe trim)\n`;
  return head + '\n' + kept.join(',\n') + ';\n';
}

/**
 * Drop rows that still reference removed products (domain quotes, website orders, etc.).
 */
function postFilterOrphanRows(file, socialProductIds) {
  let s = fs.readFileSync(file, 'utf8');

  // 1) order_items: keep only social platform products
  const socialOrderIds = new Set();
  s = s.replace(/INSERT INTO `order_items`[\s\S]*?;/g, (block) => {
    return rewriteInsert(block, (r) => {
      const f = parseSimpleFields(r);
      const itemType = unquote(f[2]);
      const itemId = String(unquote(f[3]));
      const orderId = String(unquote(f[1]));
      if (itemType === 'platform_product' && socialProductIds.has(itemId)) {
        socialOrderIds.add(orderId);
        return true;
      }
      return false;
    });
  });
  console.log('Kept social order IDs from items:', [...socialOrderIds].join(', ') || '(none)');

  // 2) orders: keep only those with social items
  s = s.replace(/INSERT INTO `orders`[\s\S]*?;/g, (block) => {
    return rewriteInsert(block, (r) => {
      const id = String(unquote(parseSimpleFields(r)[0]));
      return socialOrderIds.has(id);
    });
  });

  // 3) user_tools / site_integrations: social products only
  s = s.replace(/INSERT INTO `user_tools`[\s\S]*?;/g, (block) => {
    return rewriteInsert(block, (r) => {
      const f = parseSimpleFields(r);
      // platform_product_id is column index 5
      return socialProductIds.has(String(unquote(f[5])));
    });
  });

  s = s.replace(/INSERT INTO `site_integrations`[\s\S]*?;/g, (block) => {
    return rewriteInsert(block, (r) => {
      const f = parseSimpleFields(r);
      // platform_product_id is column index 1
      return socialProductIds.has(String(unquote(f[1])));
    });
  });

  // 4) Ensure domain data inserts are gone (belt-and-suspenders)
  for (const table of EMPTY_DATA_TABLES) {
    s = s.replace(new RegExp(`INSERT INTO \`${table}\`[\\s\\S]*?;`, 'g'), `-- ${table} data removed (non-social / orphan FK risk)\n`);
  }

  fs.writeFileSync(file, s);
  console.log('Post-filtered orphan product FKs and non-social orders.');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
