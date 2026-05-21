const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

const replacements = [
  {
    file: 'add_item.php',
    replacements: [
      {
        find: 'Drop photos here or <span class="text-purple-400" data-i18n-text="add_item.browse">browse</span>',
        replace: '<span data-i18n-text="add_item.drop_photos_here_or">Drop photos here or </span><span class="text-purple-400" data-i18n-text="add_item.browse">browse</span>'
      },
      {
        find: '<span class="text-xl">✨</span> AI Auto-Identify',
        replace: '<span class="text-xl">✨</span> <span data-i18n-text="add_item.ai_auto_identify">AI Auto-Identify</span>'
      },
      {
        find: '<span class="text-base">🔗</span> Product Details',
        replace: '<span class="text-base">🔗</span> <span data-i18n-text="add_item.product_details">Product Details</span>'
      },
      {
        find: '<span style="color:#f87171;font-weight:600;">❌ Auto-Identify failed</span>',
        replace: '<span style="color:#f87171;font-weight:600;" data-i18n-text="add_item.auto_identify_failed">❌ Auto-Identify failed</span>'
      },
      {
        find: '<span style="opacity:.7">⏳ Saving…</span>',
        replace: '<span style="opacity:.7" data-i18n-text="add_item.saving">⏳ Saving…</span>'
      }
    ]
  },
  {
    file: 'bulk_import.php',
    replacements: [
      {
        find: '✅ ZIP extracted — <?= count($extracted) ?> added to the import queue.',
        replace: '<span data-i18n-text="bulk_import.zip_extracted">✅ ZIP extracted — </span><?= count($extracted) ?> <span data-i18n-text="bulk_import.added_to_import_queue">added to the import queue.</span>'
      },
      {
        find: 'Skipped: <?= count($skipped) ?>.',
        replace: '<span data-i18n-text="bulk_import.skipped">Skipped: </span><?= count($skipped) ?>.'
      }
    ]
  },
  {
    file: 'bulk_import_csv.php',
    replacements: [
      {
        find: '<th><?= htmlspecialchars($col) ?></th>',
        replace: '<th><span data-i18n-text="bulk_import_csv.col_<?= htmlspecialchars(strtolower($col)) ?>"><?= htmlspecialchars($col) ?></span></th>'
      },
      {
        find: '<span class="font-medium"><?= $mapped_count ?></span> skipped',
        replace: '<span class="font-medium"><?= $mapped_count ?></span> <span data-i18n-text="bulk_import_csv.skipped">skipped</span>'
      },
      {
        find: '<span class="font-medium"><?= $mapped_count ?></span> failed',
        replace: '<span class="font-medium"><?= $mapped_count ?></span> <span data-i18n-text="bulk_import_csv.failed">failed</span>'
      },
      {
        find: '<span class="font-medium"><?= $mapped_count ?></span> queued for enrichment',
        replace: '<span class="font-medium"><?= $mapped_count ?></span> <span data-i18n-text="bulk_import_csv.queued_for_enrichment">queued for enrichment</span>'
      },
      {
        find: '<h3>Preview (first <?= count($preview_rows) ?> rows of data)</h3>',
        replace: '<h3><span data-i18n-text="bulk_import_csv.preview_first">Preview (first </span><?= count($preview_rows) ?><span data-i18n-text="bulk_import_csv.rows_of_data"> rows of data)</span></h3>'
      },
      {
        find: '1 Upload',
        replace: '<span data-i18n-text="bulk_import_csv.step_1_upload">1 Upload</span>'
      },
      {
        find: '2 Preview & Map',
        replace: '<span data-i18n-text="bulk_import_csv.step_2_preview_map">2 Preview & Map</span>'
      },
      {
        find: '3 Done',
        replace: '<span data-i18n-text="bulk_import_csv.step_3_done">3 Done</span>'
      },
      {
        find: '🔗 Running enrichment for <?= count($results[\'enriched\']) ?> items…',
        replace: '<span data-i18n-text="bulk_import_csv.running_enrichment_for">🔗 Running enrichment for </span><?= count($results[\'enriched\']) ?> <span data-i18n-text="bulk_import_csv.items">items…</span>'
      }
    ]
  },
  {
    file: 'bulk_import_folder.php',
    replacements: [
      {
        find: 'Ready — <span id="queue-count" class="font-medium text-white"><?= count($items) ?></span> items queued',
        replace: '<span data-i18n-text="bulk_import_folder.ready">Ready — </span><span id="queue-count" class="font-medium text-white"><?= count($items) ?></span> <span data-i18n-text="bulk_import_folder.items_queued">items queued</span>'
      },
      {
        find: '📁 <?= count($info[\'images\']) ?> image<?= count($info[\'images\'])!==1?\'s\':\'\' ?> · pending',
        replace: '📁 <?= count($info[\'images\']) ?> <span data-i18n-text="bulk_import_folder.images">image(s)</span> · <span data-i18n-text="bulk_import_folder.pending">pending</span>'
      }
    ]
  },
  {
    file: 'chat.php',
    replacements: [
      {
        find: '· Inventory-aware',
        replace: '<span data-i18n-text="chat.inventory_aware">· Inventory-aware</span>'
      },
      {
        find: '👋 Hey! I\'m your DIY Lab Planning Assistant.',
        replace: '<span data-i18n-text="chat.welcome_hey">👋 Hey! I\'m your DIY Lab Planning Assistant.</span>'
      }
    ]
  },
  {
    file: 'container_manifest.php',
    replacements: [
      {
        find: '<?= $stats[\'types\'] ?> component types · <?= $stats[\'units\'] ?> total units',
        replace: '<?= $stats[\'types\'] ?> <span data-i18n-text="container_manifest.component_types">component types</span> · <?= $stats[\'units\'] ?> <span data-i18n-text="container_manifest.total_units_count">total units</span>'
      },
      {
        find: '📄 Container QR Sticker — <?= htmlspecialchars($loc_name) ?>',
        replace: '<span data-i18n-text="container_manifest.container_qr_sticker">📄 Container QR Sticker — </span><?= htmlspecialchars($loc_name) ?>'
      },
      {
        find: 'This QR code links to the live manifest for <?= htmlspecialchars($loc_name) ?>. Print it, laminate it, and stick it on the container. Scanning with any phone camera shows the current contents in real-time.',
        replace: '<span data-i18n-text="container_manifest.this_qr_code_links_to_live_manifest_for">This QR code links to the live manifest for </span><?= htmlspecialchars($loc_name) ?>. <span data-i18n-text="container_manifest.print_laminate_stick">Print it, laminate it, and stick it on the container. Scanning with any phone camera shows the current contents in real-time.</span>'
      },
      {
        find: 'Items with location "<?= htmlspecialchars($loc_name) ?>" will appear here.',
        replace: '<span data-i18n-text="container_manifest.items_with_location">Items with location </span>"<?= htmlspecialchars($loc_name) ?>" <span data-i18n-text="container_manifest.will_appear_here">will appear here.</span>'
      },
      {
        find: '☐ = verify item is in container &nbsp;|&nbsp; Generated by DIY Lab Inventory System',
        replace: '<span data-i18n-text="container_manifest.verify_item_is_in_container">☐ = verify item is in container &nbsp;|&nbsp; Generated by DIY Lab Inventory System</span>'
      },
      {
        find: '🏷️ Print Item Labels',
        replace: '<span data-i18n-text="container_manifest.print_item_labels">🏷️ Print Item Labels</span>'
      }
    ]
  },
  {
    file: 'dashboard.php',
    replacements: [
      {
        find: '<?= $pagination[\'total_items\'] ?> unique parts',
        replace: '<?= $pagination[\'total_items\'] ?> <span data-i18n-text="dashboard.unique_parts_count">unique parts</span>'
      },
      {
        find: '<?= $pagination[\'total_qty\'] ?> across all items',
        replace: '<?= $pagination[\'total_qty\'] ?> <span data-i18n-text="dashboard.across_all_items">across all items</span>'
      },
      {
        find: '<?= count($categories) ?> component groups',
        replace: '<?= count($categories) ?> <span data-i18n-text="dashboard.component_groups_count">component groups</span>'
      },
      {
        find: 'Showing <?= count($items) ?> of <?= $pagination[\'total_items\'] ?> components',
        replace: '<span data-i18n-text="dashboard.showing">Showing </span><?= count($items) ?> <span data-i18n-text="dashboard.of">of</span> <?= $pagination[\'total_items\'] ?> <span data-i18n-text="dashboard.components">components</span>'
      }
    ]
  },
  {
    file: 'item_details.php',
    replacements: [
      {
        find: 'Quantity: <?= $item[\'quantity\'] ?>',
        replace: '<span data-i18n-text="item_details.quantity_label">Quantity: </span><?= $item[\'quantity\'] ?>'
      },
      {
        find: 'Location: <?= htmlspecialchars($item[\'location\']) ?>',
        replace: '<span data-i18n-text="item_details.location_label">Location: </span><?= htmlspecialchars($item[\'location\']) ?>'
      },
      {
        find: 'Condition: <?= $item[\'status\'] ?>',
        replace: '<span data-i18n-text="item_details.condition_label">Condition: </span><?= $item[\'status\'] ?>'
      },
      {
        find: 'Added: <?= substr($item[\'created_at\'], 0, 10) ?>',
        replace: '<span data-i18n-text="item_details.added_label">Added: </span><?= substr($item[\'created_at\'], 0, 10) ?>'
      }
    ]
  }
];

let replacedKeys = {};

replacements.forEach(task => {
  let content = fs.readFileSync(path.join(ROOT_DIR, task.file), 'utf8');
  let updated = false;
  
  task.replacements.forEach(r => {
    if (content.includes(r.find)) {
      content = content.replace(r.find, r.replace);
      updated = true;
      // Extract the new key and value for our dictionaries using regex
      const keyMatches = [...r.replace.matchAll(/data-i18n-text="([^"]+)">([^<]+)<\/span>/g)];
      keyMatches.forEach(match => {
         replacedKeys[match[1]] = match[2];
      });
    } else {
      console.log(`Could not find in ${task.file}: ${r.find}`);
    }
  });

  if (updated) {
    fs.writeFileSync(path.join(ROOT_DIR, task.file), content, 'utf8');
    console.log(`Updated ${task.file}`);
  }
});

fs.writeFileSync(path.join(ROOT_DIR, 'tests', 'manual_keys.json'), JSON.stringify(replacedKeys, null, 2));
console.log('Manual keys exported to tests/manual_keys.json');
