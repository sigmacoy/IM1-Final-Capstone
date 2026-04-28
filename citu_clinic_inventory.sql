-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2026 at 10:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `citu_clinic_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `dispensation`
--

CREATE TABLE `dispensation` (
  `dispense_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dispense_date` datetime DEFAULT current_timestamp(),
  `purpose` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dispensation`
--

INSERT INTO `dispensation` (`dispense_id`, `user_id`, `patient_id`, `dispense_date`, `purpose`) VALUES
(1, 1, 1, '2026-04-28 09:30:00', 'Patient diagnosed with bacterial infection and high fever.'),
(2, 1, 2, '2026-04-28 14:15:00', 'Allergic reaction to dust.');

-- --------------------------------------------------------

--
-- Table structure for table `dispensationitem`
--

CREATE TABLE `dispensationitem` (
  `item_id` int(11) NOT NULL,
  `dispense_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dispensationitem`
--

INSERT INTO `dispensationitem` (`item_id`, `dispense_id`, `batch_id`, `quantity`) VALUES
(1, 1, 1, 4),
(2, 1, 3, 21),
(3, 2, 2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `patient_id` int(11) NOT NULL,
  `rank` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`patient_id`, `rank`) VALUES
(3, 'University Physician');

-- --------------------------------------------------------

--
-- Table structure for table `medicine`
--

CREATE TABLE `medicine` (
  `medicine_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `reorder_level` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine`
--

INSERT INTO `medicine` (`medicine_id`, `name`, `purpose`, `reorder_level`) VALUES
(1, 'Paracetamol (500mg)', 'Analgesic / Fever Reducer', 100),
(2, 'Amoxicillin (250mg)', 'Antibiotic', 50),
(3, 'Cetirizine (10mg)', 'Antihistamine / Allergies', 30);

-- --------------------------------------------------------

--
-- Table structure for table `medicinebatch`
--

CREATE TABLE `medicinebatch` (
  `batch_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `quantity_in_stock` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date NOT NULL,
  `date_received` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicinebatch`
--

INSERT INTO `medicinebatch` (`batch_id`, `medicine_id`, `supplier_id`, `batch_number`, `quantity_in_stock`, `expiry_date`, `date_received`) VALUES
(1, 1, 1, 'LOT-A123', 500, '2027-12-01', '2026-02-10'),
(2, 3, 1, 'LOT-B456', 200, '2028-01-20', '2026-02-10'),
(3, 2, 2, 'LOT-C789', 150, '2026-06-15', '2025-12-01');

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `patient_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `patient_type` enum('Student','Employee') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`patient_id`, `first_name`, `last_name`, `email`, `gender`, `patient_type`) VALUES
(1, 'Juan', 'Dela Cruz', 'juan.delacruz@email.com', 'Male', 'Student'),
(2, 'Maria', 'Santos', 'maria.santos@email.com', 'Female', 'Student'),
(3, 'Dr. Jose', 'Rizal', 'jose.rizal@email.com', 'Male', 'Employee');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `patient_id` int(11) NOT NULL,
  `program` varchar(100) NOT NULL,
  `year_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`patient_id`, `program`, `year_level`) VALUES
(1, 'BS Computer Science', 3),
(2, 'BS Information Technology', 2);

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `name`, `email`, `address`) VALUES
(1, 'Cebu Pharma Inc.', 'contact@cebupharma.com', 'Mandaue City, Cebu'),
(2, 'MedSupply Cebu', 'sales@medsupply.ph', 'N. Bacalso Ave, Cebu City');

-- --------------------------------------------------------

--
-- Table structure for table `suppliercontactno`
--

CREATE TABLE `suppliercontactno` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `mobile_number` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliercontactno`
--

INSERT INTO `suppliercontactno` (`id`, `supplier_id`, `mobile_number`) VALUES
(1, 1, '09171234567'),
(2, 1, '0322345678'),
(3, 2, '09189876543');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `first_name`, `last_name`, `email`, `password`) VALUES
(1, 'John Arcel', 'Sabagkit', 'admin@citu.edu', '123');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `dispensation`
--
ALTER TABLE `dispensation`
  ADD PRIMARY KEY (`dispense_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `dispensationitem`
--
ALTER TABLE `dispensationitem`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `dispense_id` (`dispense_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `medicine`
--
ALTER TABLE `medicine`
  ADD PRIMARY KEY (`medicine_id`);

--
-- Indexes for table `medicinebatch`
--
ALTER TABLE `medicinebatch`
  ADD PRIMARY KEY (`batch_id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`patient_id`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `suppliercontactno`
--
ALTER TABLE `suppliercontactno`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dispensation`
--
ALTER TABLE `dispensation`
  MODIFY `dispense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dispensationitem`
--
ALTER TABLE `dispensationitem`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medicine`
--
ALTER TABLE `medicine`
  MODIFY `medicine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medicinebatch`
--
ALTER TABLE `medicinebatch`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patient`
--
ALTER TABLE `patient`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliercontactno`
--
ALTER TABLE `suppliercontactno`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dispensation`
--
ALTER TABLE `dispensation`
  ADD CONSTRAINT `dispensation_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `dispensation_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`);

--
-- Constraints for table `dispensationitem`
--
ALTER TABLE `dispensationitem`
  ADD CONSTRAINT `dispensationitem_ibfk_1` FOREIGN KEY (`dispense_id`) REFERENCES `dispensation` (`dispense_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispensationitem_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `medicinebatch` (`batch_id`);

--
-- Constraints for table `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `medicinebatch`
--
ALTER TABLE `medicinebatch`
  ADD CONSTRAINT `medicinebatch_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicine` (`medicine_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicinebatch_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`);

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `suppliercontactno`
--
ALTER TABLE `suppliercontactno`
  ADD CONSTRAINT `suppliercontactno_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
