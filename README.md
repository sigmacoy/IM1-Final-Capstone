# Clinic Inventory System

## Business Rules

1. The system shall support two user roles: Admin (Clinic Staff) and Staff (NAS Working Student). Admin users have full management privileges, while Staff users have limited operational access.

2. Each medicine in the system shall be associated with exactly one supplier, while a supplier may supply multiple medicines…

3. When a medicine is dispensed, the system shall automatically deduct the dispensed quantity from the available stock of that medicine.

4. Only users with the role of Admin or Staff shall be authorized to dispense medicines, and each dispensing transaction shall be recorded in the Dispensation table.

5. Medicines shall only be dispensed to registered patients (students or employees).

6. The system shall highlight medicines in red on the dashboard when their stock level falls below the defined reorder level.

7. The system shall prevent the dispensing of medicines whose expiry date has already passed.

8. Only Admin users shall be permitted to increase or update the stock quantity of medicines when new supplies are received.

9. A patient shall not be allowed to receive more than two dispensations of the same medicine within a seven-day period.

10. The system shall permanently record all dispensing transactions, including the timestamp, user responsible, patient recipient, and medicine dispensed, to ensure accountability and traceability.

## Entity Relationship Diagram
![Entity Relationship Diagram](.png)