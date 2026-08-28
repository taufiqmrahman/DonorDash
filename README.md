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

1. **Stockout Predictor:** Predicts exactly how many days are left until a hospital runs out of a specific blood group.
2. **Automated Resource Rebalancer:** Automatically matches hospitals running out of blood with neighboring hospitals that have too much.
3. **Donor Loyalty Analyzer:** Tracks what percentage of donors return to donate again the following year.
4. **Hospital Wastage Tracker:** Calculates the percentage of blood units that expire before they can be used at each hospital.
5. **Suspicious Activity Monitor:** Flags staff members who are registering an unusually high number of "Urgent" patients compared to average.
6. **Rare Trait Priority Scanner:** Automatically flags patients who need a genetic match that exists in less than 1% of the donor database.
7. **Emergency Nearby Resource Locator:** Instantly finds the three closest hospitals in nearby zones that have a required resource ready to go.
8. **Waitlist Priority Escalator:** Bumps up the urgency of patients who have been waiting longer than 90% of historically treated patients.
9. **Best Match Scoring System:** Gives a numbered score to potential donors based on how perfectly their blood and tissue match the patient.

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
* **Course:** CSE370 - Database Systems (Spring 2026, Section 03, Group 03)
* **Team Members:** 
  * Taufiq Mustafizur Rahman (ID: 24301485)
  * S. M. Tahmidul Hoque (ID: 24341017)
  * Mohammad Ahonaf Bin Ameen (ID: 24301078)