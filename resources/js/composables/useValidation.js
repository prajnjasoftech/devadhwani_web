/**
 * Validation composable for common field validations
 */

// Indian mobile number: 10 digits starting with 6, 7, 8, or 9
export const isValidIndianMobile = (value) => {
  if (!value) return true; // Allow empty (use required for mandatory fields)
  return /^[6-9]\d{9}$/.test(value);
};

export const mobileValidationMessage = 'Enter a valid 10-digit mobile number';

// Email validation
export const isValidEmail = (value) => {
  if (!value) return true;
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
};

// Check if value is numeric
export const isNumeric = (value) => {
  if (!value) return true;
  return !isNaN(value) && !isNaN(parseFloat(value));
};

export default {
  isValidIndianMobile,
  mobileValidationMessage,
  isValidEmail,
  isNumeric,
};
