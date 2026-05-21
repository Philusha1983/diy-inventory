# 🔑 Getting API Keys

The DIY Lab Inventory System requires **one** API key to power its AI features (Auto-Identify, Creative Engine, Blueprints, and Lab Assistant Chat). You can choose between **Google Gemini** (recommended) or **OpenAI GPT-4o**.

---

## Option A — Google Gemini (Recommended)

The app uses the **Gemini 2.5 Flash** model, which supports both high-fidelity image analysis (vision) and text generation.

### 1. Free Tier Key (AI Studio)
Ideal for personal use and developer testing.

1. Navigate to **[Google AI Studio](https://aistudio.google.com/app/apikey)**.
2. Sign in using your Google account.
3. Click **"Create API Key"** and select/create a project.
4. Copy the generated key (starts with `AIza...`).

> 💡 **Free limits:** ~1,500 requests per day. The quota resets daily at midnight Pacific Time. This is more than sufficient for a single-maker lab setup.

### 2. Paid Tier Key (Google Cloud Console)
Required if you hit free-tier quotas or need higher rate-limit throughput.

1. Navigate to the **[Google Cloud Console → APIs & Services → Credentials](https://console.cloud.google.com/apis/credentials)**.
2. Verify that **Billing is enabled** on your active Google Cloud project.
3. Enable the **Gemini API** for your project:
   - Go to **APIs & Services → Library** → search **"Gemini API"** → click **Enable**.
4. Click **`+ Create Credentials`** → select **`API key`**.
5. *(Recommended)* In the API restrictions dropdown, restrict the key to the **Gemini API** only.
6. Copy the key.

> ⚠️ **Key Identification Note:** Both free and paid keys start with the exact same prefix (`AIza...`). The billing tier is determined entirely by whether billing is enabled on the Google Cloud project to which the key belongs.

---

## Option B — OpenAI GPT-4o

GPT-4o also supports multi-modal vision and text. This is a pay-as-you-go service.

1. Navigate to the **[OpenAI Platform API Keys Console](https://platform.openai.com/api-keys)**.
2. Register or log in to your account.
3. Click **"Create new secret key"**, provide a name, and save the key (starts with `sk-...`).
4. Ensure you have added credit to your account (e.g., $5 to $10 to start) in the Billing settings.

---

## Saving Your API Key in the Web UI

Do not hardcode your API key in any PHP files. Save it securely through the User Settings interface:

1. Open your browser and navigate to the User Settings page:
   👉 `http://localhost:8080/settings.php`
2. Scroll down to **Section 4: AI Configuration**.
3. Select your active provider (**Gemini** or **OpenAI**).
4. Paste your key in the **API Key** text field.
5. Click **Save Configuration**.

The key will be stored in the database's `settings` table and loaded dynamically.

---

⬅️ **[Back to README](../README.md)**
