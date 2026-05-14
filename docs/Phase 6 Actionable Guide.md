# **Phase 6: Inventory Population & Physical Audit**

This phase is about the logistics of scanning your lab. Now that the "magic" auto-fill works, we need a strategy to get hundreds of parts into the system without burning out.

### **Task 6.1: Physical Batching**

Don't scan randomly. Group your components by type before you start the software. This makes the "Category" and "Location" fields easier to manage.

1. **Group A:** Microcontrollers (Arduinos, ESPs, Pi Picos).  
2. **Group B:** Sensors (Temperature, Motion, Ultrasonic).  
3. **Group C:** Actuators (Servos, Motors, Relays).  
4. **Group D:** Passives (Resistor kits, Capacitors).

### **Task 6.2: The Scanning Sprint**

Using your phone (connected to your local server IP), follow this workflow for each component:

1. Open add\_item.php.  
2. **Take 3 Photos:**  
   * One top-down (to see the main chip/markings).  
   * One of the pinout/labels.  
   * One of the packaging (if it has a barcode or model number sticker).  
3. Click **✨ Auto-Identify**.  
4. **Wait:** Let the AI do the heavy lifting of typing specs.

### **Task 6.3: Quality Control (The "Human in the Loop")**

AI is smart, but it can hallucinate specs. Before clicking "Save Component":

* Check the **Model Number**. If it's a generic "HC-SR04," ensure the AI didn't call it something else.  
* Check the **Quantity**. You'll need to manually adjust this if you have a bag of 10 sensors.  
* Verify the **Specs**. Ensure the operating voltage listed matches what you know (e.g., 3.3V vs 5V).

### **Task 6.4: Physical Mapping (Bin Labels)**

A database is useless if you can't find the part.

1. Assign every drawer, box, or bin a code (e.g., BIN-A1, DRAWER-04).  
2. As you save the item in the software, physically place it in that bin.  
3. Ensure the **Location** field in your form matches that code exactly.

### **Phase 6 Test Case (The "Search & Find" Test)**

1. Ask a friend (or imagine yourself a month from now) to find a specific part: "I need a 5V Relay."  
2. Open your dashboard.php and use the browser's search (Ctrl+F) or a search bar if you've added one.  
3. Find the item, look at the **Location** field.  
4. *Success:* You can walk directly to a physical bin and put your hand on that specific part in under 30 seconds.