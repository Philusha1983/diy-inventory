const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const missingPath = path.join(ROOT_DIR, 'tests', 'missing_in_en.json');
let missing = {};
if (fs.existsSync(missingPath)) {
  missing = JSON.parse(fs.readFileSync(missingPath, 'utf8'));
}

Object.assign(missing, {
  "item_details.unit": "unit",
  "item_details.units": "units",
  "item_details.ai_enrichment": "AI Enrichment",
  "item_details.enriched": "Enriched",
  "item_details.refetch_refresh": "Re-fetch & Refresh",
  "item_details.enrich_from_web": "Enrich from Web",
  "item_details.find_projects": "Find projects using this component",
  "item_details.ask_ai": "Ask AI about this component",
  "item_details.add_photos": "Add photos →",
  "item_details.connecting_to_urls": "Connecting to URLs…",
  "item_details.fetching": "Fetching…",
  "item_details.enriched_reload": "Enriched — Reload to see",
  "item_details.retry": "Retry",
  "add_item.save_changes": "💾 Save Changes",
  "add_item.add_to_inventory": "➕ Add to Inventory",
  "add_item.please_select_photo": "Please select or drop at least one component photo first.",
  "add_item.analysing_images": "Analysing images… please wait.",
  "add_item.identification_complete": "Identification complete! Review the fields below and click Save.",
  "add_item.identification_failed": "Identification failed. Please try again.",
  "add_item.save_failed": "Save failed: "
});

fs.writeFileSync(missingPath, JSON.stringify(missing, null, 2), 'utf8');
console.log('Appended item details and add item JS keys');
