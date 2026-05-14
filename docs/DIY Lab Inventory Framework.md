# **DIY Lab Inventory & Project Orchestrator**

This framework outlines a lightweight, self-hosted solution for managing a DIY laboratory inventory. The goal is to bridge the gap between physical components and creative project execution through automated identification and intelligent suggestion.

## **Technical Architecture**

The system is designed to be efficient and portable, running on existing web infrastructure without the overhead of complex content management systems.

| Component | Specification |
| :---- | :---- |
| **Server Environment** | PHP-based web server |
| **Database** | MySQL (managed via phpMyAdmin) |
| **Front-end** | HTML5, CSS3, and JavaScript |
| **Access Control** | Simple password protection |
| **External Integrations** | RESTful API connectivity (e.g., Google Gemini, OpenAI) |

## **System Configuration & Settings**

To facilitate AI features, the system requires a dedicated configuration module:

* **API Provider Selection:** A toggle to choose between Gemini, OpenAI, or other computer vision providers.  
* **Credential Management:** Secure input fields for API keys, stored in the database or a protected server-side configuration file.  
* **Usage Monitoring:** Optional logging of API calls to monitor costs or token usage.

## **Core Workflows**

### **1\. Data Ingestion & Identification**

This workflow manages the transition of physical hardware into the digital database. The system captures component details through imagery and metadata.

* **Multi-angle Capture:** Support for uploading several photos per item to ensure all labels and pins are visible.  
* **AI-Driven Enrichment:** Utilising the configured AI API (e.g., Gemini Pro Vision) to identify model numbers, specifications (e.g., voltage, amperage), and part descriptions directly from uploaded images.  
* **Structured Storage:** Automatic generation of database records including quantity and initial health status.  
* **Manual Override:** A validation step where the user confirms or edits the AI-generated specifications before the record is finalised.

### **2\. Inventory Management Dashboard**

A centralised interface for overseeing lab resources.

* **Status Tracking:** Categorisation by condition (New, Used, Refurbished).  
* **Quantity Control:** Real-time tracking of component counts (e.g., number of Raspberry Pi units or sensors).  
* **Quantity Control:** Real-time tracking of component counts (e.g., number of Raspberry Pi units or sensors).  
* **Version Management:** Monitoring specific hardware revisions or firmware versions for complex modules.

### **3\. Creative Engine & Project Discovery**

The system analyses the inventory to propose actionable builds, ranging from standard utilities to experimental designs.

* **Feasibility Analysis:** Suggesting projects that can be completed using 100% of currently owned parts.  
* **Acquisition Planning:** Identifying projects that require 1–2 additional parts, with integrated links for quick ordering.  
* **Diverse Project Scopes:** Suggestions include smart irrigation systems, environmental monitors, automated soundscapes, or interactive robotics.  
* **Project Metadata & Classification:** For every suggested project, the system will provide key metrics to help with selection:  
  * **Complexity Level:** Ranked from Beginner (plug-and-play) to Expert (requires custom PCBs or advanced coding).  
  * **Estimated Duration:** Predicted build time (e.g., \<1 hour, afternoon project, or multi-day build).  
  * **Required Tooling:** Identification of necessary equipment not in the inventory (e.g., Soldering iron, 3D printer, or Multimeter).  
  * **Skill Domains:** Categorisation by primary focus, such as Embedded Systems, Mechanical Design, or Data Visualisation.  
  * **Safety & Power Profile:** Highlighting projects involving high-voltage AC or sensitive LiPo battery handling.  
* **AI-Generated Project Blueprints:** For any selected project, the system triggers the configured AI to generate a comprehensive technical guide:  
  * **Step-by-Step Manual:** Sequential assembly instructions and wiring guidance.  
  * **Code Repository:** Automated generation of necessary firmware, scripts, or libraries (e.g., Arduino C++, Python) tailored to the specific hardware on hand.

### **4\. Interactive Planning Assistant**

A conversational interface for custom brainstorming.

* **Stock-Aware Brainstorming:** Users can describe a goal (e.g., "I want to build a gift for my child") and the assistant suggests designs based on existing inventory.  
* **Component Mapping:** Mapping high-level ideas to specific pins and modules available in the lab.

## **Background Prompts examples:**

### **1\. Component Identification Prompt**

*Triggered during image upload.*

"Analyse the attached photos of a DIY lab component. Identify its type, model, and any relevant specifications, such as part numbers, voltage, or amperage. Consider markings on the component or packaging. Provide a short description and format the output as a structured JSON object including: 'item\_name', 'model', 'specs', 'category', and 'description'."

### **2\. Project Discovery Prompt**

*Triggered when browsing for new project ideas.*

"Review the following list of available lab components:

![][image1]. Suggest 5 creative projects I can build. For each project, provide:

1. A descriptive title.  
2. Complexity Level (Beginner/Intermediate/Expert).  
3. Estimated Duration.  
4. List of components used from stock.  
5. Any 'Missing Components' needed (maximum 5). For each missing item, provide a direct search link to Amazon and AliExpress to facilitate quick acquisition.  
6. Primary Skill Domain (e.g., Robotics, IoT).  
7. Safety Warning (if high voltage or heat is involved)."

### **3\. Project Blueprint & Code Prompt**

*Triggered when a specific project is selected for assembly.*

"Generate a comprehensive technical guide for the project:

![][image2]. Use the following components from my inventory:

![][image3].

Provide:

1. A detailed step-by-step assembly manual with wiring instructions.  
2. A complete, production-ready code block (e.g., Arduino C++ or Python) that is specifically mapped to the pinouts of the components provided.  
3. List of required tools (e.g., soldering iron, breadboard)."

### **4\. Interactive Assistant Prompt**

*Triggered during a chat session.*

"You are the DIY Lab Planning Assistant. You have access to my current inventory:

![][image1]. The user wants to achieve the following goal:

![][image4].

Suggest how they can achieve this using only the parts available or by adding minimal new hardware. Explain which specific modules should be used and how they will interact."

[image1]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmwAAAAvCAYAAABexpbOAAADb0lEQVR4Xu3czWskRRQA8ERRF/xEN4RN0plOHAhkL6vxoAf/BVEEQTx40IsHPYiIgnoTBL3KirCyrHgSbyKIF0GQVRAR9OJNUHRPgoLirl+vlq6kUraLmZ1kZszvB4+qelXVPb2bDK97kszNAQAAjMV8nQAAAIARuL8EAAAmwK3I5B2y/4NDdrkAAAAwS9y2AwAATDk3bgAA7Be1JuyV7xoAAAAAAABgj3zMCADAjFPSAgCXTUEBAACwi9skAACA2TYYDP5qmubWOn9Q4vyP1LlxStfXtu3DdT5JcxHH6nxtkv8+AAAXi5Y6d5D2+/zjOP44jgEAMJLV1dWnJ12M7Of54/o2x3H8cRwDAGAk3UeCL+fxcDi8Jpr5Lv9bXlOs/65bk8fl3H1t235d5qP9JuKhiBejeDodqfloX+vmnoz4IPZ8mvr5ODH/ftpTHSe9ni8jPor1bbSnIn1l0zR3V69hV2EV43MRZ8tcEvvumeuus8zH+OdorlhZWVleWlo6Gqd6LOLZ7vzbrxEA4MD0FCy/1/mqfyzivXouipojfXuifT4KsJPVnnPVuu1fj4rxWxFvlvMbGxvXLywsXJf63fk/W1xcvLY4xz/OW47T/jKXxGv6ZGtr66q+9V37RpF7J9Y/sbMKgMvjt2JhT+qCJYni5IHIf5zH9ZqqQPo15yLORjy3trY22Fm9e30q7OL4x/vm/m2c9vTNJXGsFyL/duo3TXNH9H8s5+s96clZMXd+OBwulPPddVyMMleuAQA4MFGIPNVXjKRcfioVBc0NMf6zns/9zc3Nq3MuCqubdlbtqIqfXOC9Ws5F4fV4vbYe13N1Lvo/RdxejG+r9/QdL9pHu/areq7sR7F3c84B7A9PnoBKKkQiXu/LF/13I+6K+KGeH3QfnyZRrJ2I8ZlizfZcXfysr6/fmPrdx5rfpn7sf7Bv7Vz37hX9+yM+zHNZ5C4U/bo4+yXiizxOPysXcW85H+M7i/H3Rf980c/X+3nOAQDwHy0vL9+S2u5J4B/1PAAAExQF2iv5CV39dA3gf8OnpMCsa9v2pYhn6jwAAAAAXJon5MD08c7EDPHlCgAAU0FpDgAAAAAAAAAAAAAAAAAAAAAATD1/MAmYLO9CTBVfkAAAAADAYeKZKAAAAMCoPFkBAAAAAAAAAAAAYFR/A5XO0Mu1EY3MAAAAAElFTkSuQmCC>

[image2]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmwAAAAvCAYAAABexpbOAAADXklEQVR4Xu3cPYhcVRQA4AQV/xpBYXFm3s7M7lpYiOISsLGyEoJlUikKopUoAUHERixEghCsQhqR2Ke0ECFBUtvbGEgCgRUbFXHRrOfCe+vh5q1ZM7A7O/N9cLn3nHvfnZll93HmvZk9dowFcrxOwPLw6w/si5MFAADAAvEmDwCAeaAuZW74ZQQAAAAAAAAAgJn5GA7AvXIGBQAAAAAAAIB54k4+AACwPLwDAgAAAAAAABbPYDB4ZDKZPFbnl4G7wACLyhl+KYzH44+i7aT4Yo7vVdljOp2O6/x+ra2trZY+9vkh2s0otF5omubFsm+0p7txPqYn/qKKd0aj0cM5BwvLORxgcZQiZnV19dM61xVMhyEefysKtOfa8a2UvxTtdorPduM+PQXczIUoAMCB6yti+nIHaa/HL/mmaV5J8c08X4tC9J0c77UvLC1X4QCOhrqIiXg7Cp3Xyjj6t9rc99Edz2vLla7BYPDEcDh8PMZbKf9T2++ubdeUuOxxucuPRqOnIv6tXX+7XFWL/v2yNtqZbl2nfq6deJ5XSh/zf7b9mWjflj72fKPkon894qv5uLLf+vp6U1qMp3kOWFaqWDgc/vb2FEXM6VK0RMHzZfQXon81z0fuj8itRf/kxsbGg9F/1+Z3SpzWdYXU7k87F1fV+Poe+V9KH0XcMzH+ucsn9+f1WeRfjtfyXvSXUq4uRHfKlw765uvXDUeDkxvAUihFSxQ6X9X5LOZ/rHN9xVCOowB6KXLX2rmz0bajfdI0zYluTcS/Rvt896B/81vT6fTZnvw30f6q8536OewnLo8fz/VUzgMAzJW6iOlTr4n4+ZwrRVhdSEW8Hfn1dvx1FH3n8nybL3vccYmgfrxOyUdxdbLOd7rjNjc3H8hxHHM+x506BgCYR/ftp2jpW5Nzd5uv43F76zP6D6OQezvl/85rx+nboDnfJ+bejL0+GLe3RFdWVh6N8Y0o1t5Na34vt35TvLtfHPtZtIe6GABg4f1XcQUAwCEpRdpkMvm4XBkbDoejeh4AgDs/Dnagyr/4uNsXGAAAAFgCh/v2FAAAAAAAAAAAAACAOeCj5QAAAAAAAAAAAAAAMBMfzQcAAAAAAAAAAAAAAACA/8u38wAAAAAAAAA4cma42T3DocBi+Acf5roL64hmRAAAAABJRU5ErkJggg==>

[image3]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmwAAAAvCAYAAABexpbOAAAEaUlEQVR4Xu3cS4tcRRQA4MT4iK+NGiMz0495yOiAiowiEfE3KEEQHwRFkOALFBTdqCi40IAQUERwIYKroG5cCYq4CAqKG0VBQY2IiK5EJSEZT7VVsVJ0Zno6E/KY74Oiqk7VPbf7DkxO7u2eDRuAE25jGwAAAAAAAAAAgHXKZ2kAAAAAAAAAYI14CA+wZvxKBQAAADhmbrEApzO/4wAAAAAAAAAAYF3zESIAAAA4ifT7/c3R7m/jq9Htdhfb2PEU55tpYwAAx12v11uKwmlHG09i7cG03sZbnU5nto2tJPIeyLnHesgSr/mZaB+38VHFsfe0sZXU12LYtRknJwCnorH+6YLxtUXHOFabI/a/HcXNzja+GumcW7duPb+Nj6Lb7T4U7d02vpI4559trBg3JwDAsqLAWFhtsTXManOsdv8wx5IjH3tGG19JXK+b2lgxbk4AgGVFkfFrtL1tPOn3+8+2RdHU1NRVEbsv+smJiYlL0l2yaE+mfdEeLfuisLk75u+ncZsj1h4p+6PdWq+VvZHzvRLL5xzc2Yr+j3ZvNd8XbXsef1bF/44cl2/ZsuWCGO+K9lg5f5znirRncnLy4pxvY/QflWNnZma6Kdfi4uJZ9fnaazNqzrzn82jb0uPTaJfF+OtoB8p6yZnENT4vYq+k8fT09M31GgCwTqQCYX5+/sIh8Q/LehMfzKN/o4rtiSLs4TKPwuLqUoAkUZS8FvNdZZ60eZM47pqIf5XXvyvxem+M/0l95NwR40+qeCo8n8/TTfF6Hs/xQ+kuYj7m2/Klgfb8zTl+Okq8vPdlr82wecmZi8alaDeWte6QczTzM9M4FY31GgCwTrQFQpLumKU+3UHrVYVXEgXPq7ngWLbISIVJNT8Yx73Q7qnnRcndy3fIUh/H/hjt6VJ4lX1TU1PnNsftrgvHEq/nOZa+LPBONX8x2v5oz3U6neur+N5ov1Tzw7naazNqzrxWX7t7e7kAbNeS+FncnmJtHABYJ6IIuLYtBGL+UjVeWlhYODv6N/P8zunp6fmyVu9LfRRQF7Vrw+Y5tntI7LdqPDgm+n1RtNzy/64j11NBU89bw+I5NvisWYy3RXsr8rzcbBvsK++3zKO9XsbNtRkpZ1K/pjSOfZvTOF2/mB8qaxG/IQrQ2/K+O0ocAFhHogj4K9qXZR4FwqdtMZH7bfU8j/cP2fdF7n+em5s7J4+/qe+MJbOzs53oNjWxS8tduDjmymgPpHH+DNcPZV+MD+Z+qX5EWOJJ/tzZU2VfiUf+J+I99qvX+31Za97b4HNyKUccc1dZ7/336HfwLdAqxxHXZoSc2/vVnyJp9nzQ6XSui/73aq0UgYevAQDAcder7qQBAHASSZ9DS3eN6rtKAKwRf8cVWCtRrO1pYwAAAAzjjgwAoCIAAAAAAAAA4ETxxBoAOCqFAgAADKFQBgAAAAAAAAAAAAAAYAS+hgIAAAAAAABrwIM3AACA04j/5J0O/BQBAAAAAABYnidKAHBK+RdQZy64S2A8UAAAAABJRU5ErkJggg==>

[image4]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAmwAAAAvCAYAAABexpbOAAAC3ElEQVR4Xu3cvYsUSRgHYD8QDS7zNnCXnumBDQ68yMXARAwFw8tNPDDxPxARPNjQf0C4QwQFNTIzEAQjjdQzMNtY5M7k4BK/3tKu27KuxV2cdZye54Giq96p7p6Z3a397UzP7toFMEW76wIAAAAAAAAAAMCCcP0UAAAAAAAAAAAA8C24ZpFP+IYA2CYL51fw5AEAAAAAAAAAMHMuYunlaQEAdo6kAUyflQUAgAHZOx6Pz49Go9NpEP0z9YTtaJrm57oGADAIEZRuR3tX1a5EkLpb1qZlbW1tXzpfHP9wrrVt+yja03LedsS+B+rHAABfZXHeNVucRzrPUtCJdqGq3SjH09QXrKL218rKysG6vlUR2K5GAFyv67BQLLnD5OsKJJ8JUP+rTUM67mQyGffUb9e17dip+7szrL7ATFh8YJ71hZ22bW/WtZj3trvtRFHek/cvt9GeRXsQw925Hvud7TtXn3Su5eXlH9OrbtF/WdTzOf4uj7XV4wIAzJ0IOpe2GnbyvNFo9LCudf03abu0tPRDqkc7tLq6uj+29/LcrZwrzUn7leO8bZpmOfUj/P0U43/qOQAAg5OCTgSwi2Utxn+U4ywHrnEXzGJ7qtt/vb7+rC9Adfveqmrnov0b7Vo5r5rzX2ArahvRjnX936P9lm8DABiUFILSpzar2p/luKu9KPo5QP0a7fHmrE116Opq1z9TL4PYkXLcNM3RGL/umZfvw+Xcb9v2Tr4dAGAwIuScjMDzPI/7AlUS9SddN12TtlHUyxD1qtv+Eu1+rpfS/HQtWx5PJpPj9Tn7glnZj+353M//IqSrf3jFDQAAYOZ83A8AAAAAgO+SF7AB5oUVGwCA4ZFyAQAAAAAAAGCAXBAAAAAwc/40AwD4IpEJAOaIX9yLydcdmAFLD+wgP2AAAAAAAAAADIi3wQEAmCoBc4o8mQAAAAAAAAAAAHzkijLgA4sBAAB8gdAMwPfuPXBlkR9e2rAaAAAAAElFTkSuQmCC>