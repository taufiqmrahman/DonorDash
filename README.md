# 🩸 DonorDash

> **Precision matching when seconds matter.**
> 
> DonorDash is a centralized, highly specialized healthcare logistics platform designed to manage the complex allocation of critical and scarce medical resources, specifically blood, rare plasma and organ transplants. 

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)

## 📖 Overview
Rather than functioning as a standard patient-doctor portal, DonorDash operates entirely as a high-stakes matching engine. It cross-references patient needs with a massive network of donors and hospitals, ensuring that life-saving resources are distributed rapidly, fairly and safely based on strict biological compatibility and calculated medical urgency.

## ✨ Core Features
The system is built on a highly normalized relational database and powered by 9 advanced SQL-driven operational modules:

1. **Bio-Marker Compatibility Matrix:** Automatically cross-references a waitlisted patient’s genetic profile and blood type with a donor to find an exact genetic match.
2. **Emergency Dispatch Filter:** Allows administrators to bypass standard queues during emergencies and instantly generates a list of eligible, fully recovered donors located in the same geographic zone.
3. **Cross-Hospital Resource Transfer:** Calculates which neighboring hospital in the network currently possesses the highest surplus of a required blood group.
4. **Live Regional Shortage Forecaster:** Monitors total resource supplies across the city and triggers visual warnings if a specific supply drops below a safe emergency baseline.
5. **Temporal Donor Lockout System:** Enforces mandatory medical recovery windows by mathematically calculating the time elapsed since a citizen's last donation, physically blocking early re-registration.
6. **Hospital Resource Utilization Index:** Evaluates operational performance by comparing the number of successful donations processed against the size of active patient waitlists.
7. **Dynamic Urgency Waitlist:** Prioritizes patients dynamically based on a mathematically calculated combination of medical severity and total wait time.
8. **Automated Waitlist Pruning:** A transaction engine that automatically updates a donor's recovery status once a match is confirmed and securely archives the treated patient from the active waitlist.
9. **Rare Trait Alert System:** Detects when incredibly rare genetic tissue profiles are registered and instantly triggers alerts for patients experiencing extended wait times for that specific match.

## 🛠️ Architecture & Tech Stack
This project enforces strict referential integrity and role-based access control. All database interactions utilize raw SQL queries without the use of Object-Relational Mappers (ORMs) to demonstrate core database management competencies.

* **Frontend:** Vanilla HTML, CSS, JavaScript, Tailwind CSS v4
* **Backend:** Vanilla PHP
* **Database:** MySQL (Structured via an Enhanced Entity-Relationship / EER model)
* **Environment:** XAMPP Server

## 🔐 Access Control Roles
The system utilizes Disjoint Specialization to strictly categorize internal staff roles:
* **System Admin:** Full management access for registering staff and managing hospital facilities.
* **Medical Officer / Doctor:** Medical access for assigning patients to waitlists, updating urgency scores, and confirming matches.
* **Data Clerk:** Data entry access for registering citizen bio-data and recording donation logs.

## 🎓 Academic Context
This platform was developed as a comprehensive Database Systems engineering project at **BRAC University**.
* **Course:** CSE370 - Database Systems
* **Team Members:** Taufiq Mustafizur Rahman, S. M. Tahmidul Hoque, Mohammad Ahonaf Bin Ameen
