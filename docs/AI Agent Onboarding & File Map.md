# **Project Onboarding: DIY Lab Inventory & AI Orchestrator**

## **1\. Project Overview**

This system is a self-hosted, lightweight hardware inventory manager built for DIY makers. It goes beyond simple tracking by using Computer Vision (Gemini/OpenAI) to identify components from photos and a "Creative Engine" to suggest projects based on current stock.

**Core Tech Stack:** PHP (Vanilla), MySQL, JavaScript, Tailwind CSS (optional/embedded), and REST APIs for AI integration.

## **2\. Document Directory**

Use this list to find specific logic, schemas, or instructions.

| File Name | Purpose | Key Content |
| :---- | :---- | :---- |
| diy\_lab\_inventory\_framework.md | **The Vision** | High-level requirements, workflows, and system architecture. |
| execution\_plan\_diy\_lab.md | **The Roadmap** | Phased development steps and testing milestones. |
| phase\_1\_guide.md | **Foundation** | Database SQL schema (inventory, settings) and password gate. |
| phase\_2\_guide.md | **CRUD Operations** | Basic Dashboard and Add/Delete PHP logic. |
| phase\_3\_guide.md | **Visual Storage** | Multi-angle image upload and JSON path storage logic. |
| phase\_4\_guide.md | **AI Connectivity** | UI-based Settings page and ai\_helper.php proxy. |
| phase\_5\_guide.md | **Automation** | identify\_api.php and JS auto-fill logic for hardware ID. |
| phase\_6\_guide.md | **Data Strategy** | Best practices for physical audits and location mapping. |
| phase\_7\_guide.md | **Creative Engine** | projects.php logic for AI-driven project discovery and buying lists. |
| phase\_8\_guide.md | **Conversational UI** | chat.php for stock-aware brainstorming with AI. |
| phase\_9\_guide.md | **Deployment** | Server migration, SSL setup, and production security. |

## **3\. Critical Logic Patterns for the Agent**

* **The Single-File Mandate:** For any specific UI feature (like chat.php or settings.php), keep HTML, CSS, and JS in a single file to maintain the "slim" architecture.  
* **Database Connectivity:** Always require db.php for PDO-based database interactions.  
* **Image Storage:** Images are stored as physical files in /uploads, while the database stores their paths as a JSON-encoded string in the image\_paths column.  
* **AI Communication:** All AI calls should be routed through call\_ai\_api() in ai\_helper.php to ensure the API key is retrieved from the settings table rather than hardcoded.

## **4\. Agent Instructions**

1. **To Modify UI:** Refer to Phase 2 or Phase 3 for standard layouts.  
2. **To Modify AI Logic:** Refer to Phase 4 (infrastructure) and Phase 7 (prompts).  
3. **To Troubleshoot/Debug:** Check db.php for connection issues and Phase 5 for API response handling.