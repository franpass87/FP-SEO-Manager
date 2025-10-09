/**
 * Tests for Bulk Auditor Event Handlers
 * 
 * @package FP\SEO
 */

import { shouldIgnoreEvent, handleRowClick, handleKeyboardNavigation } from './events';

describe('Bulk Auditor Events', () => {
	
	describe('shouldIgnoreEvent', () => {
		
		test('should return false for events with no target', () => {
			const event = { target: null };
			expect(shouldIgnoreEvent(event)).toBe(false);
		});

		test('should return true when clicking on a button', () => {
			document.body.innerHTML = '<button id="test">Click me</button>';
			const button = document.getElementById('test');
			const event = { target: button };
			expect(shouldIgnoreEvent(event)).toBe(true);
		});

		test('should return true when clicking on a link', () => {
			document.body.innerHTML = '<a href="#" id="test">Link</a>';
			const link = document.getElementById('test');
			const event = { target: link };
			expect(shouldIgnoreEvent(event)).toBe(true);
		});

		test('should return true when clicking on an input', () => {
			document.body.innerHTML = '<input type="text" id="test" />';
			const input = document.getElementById('test');
			const event = { target: input };
			expect(shouldIgnoreEvent(event)).toBe(true);
		});

		test('should return true when clicking inside an interactive element', () => {
			document.body.innerHTML = '<button><span id="test">Text</span></button>';
			const span = document.getElementById('test');
			const event = { target: span };
			expect(shouldIgnoreEvent(event)).toBe(true);
		});

		test('should return false when clicking on non-interactive element', () => {
			document.body.innerHTML = '<div id="test">Text</div>';
			const div = document.getElementById('test');
			const event = { target: div };
			expect(shouldIgnoreEvent(event)).toBe(false);
		});
	});

	describe('handleRowClick', () => {
		
		afterEach(() => {
			document.body.innerHTML = '';
		});

		test('should do nothing if event should be ignored', () => {
			const container = document.createElement('table');
			container.innerHTML = `
				<tr id="test-row" tabindex="-1">
					<td><input type="checkbox" name="post_ids[]" value="123" /></td>
					<td>Content</td>
				</tr>
			`;
			document.body.appendChild(container);
			
			const row = document.getElementById('test-row');
			const checkbox = row.querySelector('input');
			const onToggle = jest.fn();
			
			// Click on checkbox itself (interactive element)
			const event = { target: checkbox };
			
			handleRowClick(event, row, onToggle);
			
			expect(onToggle).not.toHaveBeenCalled();
		});

		test('should toggle checkbox when clicking on row', () => {
			const container = document.createElement('table');
			container.innerHTML = `
				<tr id="test-row" tabindex="-1">
					<td><input type="checkbox" name="post_ids[]" value="123" /></td>
					<td>Content</td>
				</tr>
			`;
			document.body.appendChild(container);
			
			const row = document.getElementById('test-row');
			const checkbox = row.querySelector('input');
			const td = row.querySelector('td:last-child');
			const onToggle = jest.fn();
			
			checkbox.checked = false;
			const event = { target: td };
			
			handleRowClick(event, row, onToggle);
			
			expect(checkbox.checked).toBe(true);
			expect(onToggle).toHaveBeenCalledWith('123', true);
		});

		test('should uncheck checkbox when clicking on checked row', () => {
			const container = document.createElement('table');
			container.innerHTML = `
				<tr id="test-row" tabindex="-1">
					<td><input type="checkbox" name="post_ids[]" value="123" /></td>
					<td>Content</td>
				</tr>
			`;
			document.body.appendChild(container);
			
			const row = document.getElementById('test-row');
			const checkbox = row.querySelector('input');
			const td = row.querySelector('td:last-child');
			const onToggle = jest.fn();
			
			checkbox.checked = true;
			const event = { target: td };
			
			handleRowClick(event, row, onToggle);
			
			expect(checkbox.checked).toBe(false);
			expect(onToggle).toHaveBeenCalledWith('123', false);
		});

		test('should focus the row after toggling', () => {
			const container = document.createElement('table');
			container.innerHTML = `
				<tr id="test-row" tabindex="-1">
					<td><input type="checkbox" name="post_ids[]" value="123" /></td>
					<td>Content</td>
				</tr>
			`;
			document.body.appendChild(container);
			
			const row = document.getElementById('test-row');
			const td = row.querySelector('td:last-child');
			const onToggle = jest.fn();
			
			row.focus = jest.fn();
			const event = { target: td };
			
			handleRowClick(event, row, onToggle);
			
			expect(row.focus).toHaveBeenCalled();
		});

		test('should do nothing if row has no checkbox', () => {
			const container = document.createElement('table');
			container.innerHTML = '<tr id="test-row"><td>No checkbox</td></tr>';
			document.body.appendChild(container);
			const row = document.getElementById('test-row');
			const td = row.querySelector('td');
			const onToggle = jest.fn();
			
			const event = { target: td };
			
			handleRowClick(event, row, onToggle);
			
			expect(onToggle).not.toHaveBeenCalled();
		});
	});

	describe('handleKeyboardNavigation', () => {
		
		afterEach(() => {
			document.body.innerHTML = '';
		});
		
		function setupTable() {
			document.body.innerHTML = `
				<table>
					<tbody>
						<tr data-fp-seo-bulk-row tabindex="-1" id="row1">
							<td><input type="checkbox" name="post_ids[]" value="1" /></td>
						</tr>
						<tr data-fp-seo-bulk-row tabindex="-1" id="row2">
							<td><input type="checkbox" name="post_ids[]" value="2" /></td>
						</tr>
						<tr data-fp-seo-bulk-row tabindex="-1" id="row3">
							<td><input type="checkbox" name="post_ids[]" value="3" /></td>
						</tr>
					</tbody>
				</table>
			`;
		}

		test('should move focus to next row on ArrowDown', () => {
			setupTable();
			
			const row1 = document.getElementById('row1');
			const row2 = document.getElementById('row2');
			const event = { key: 'ArrowDown', preventDefault: jest.fn() };
			
			row2.focus = jest.fn();
			
			handleKeyboardNavigation(event, row1, '[data-fp-seo-bulk-row]', null);
			
			expect(event.preventDefault).toHaveBeenCalled();
			expect(row2.focus).toHaveBeenCalled();
		});

		test('should move focus to previous row on ArrowUp', () => {
			setupTable();
			
			const row1 = document.getElementById('row1');
			const row2 = document.getElementById('row2');
			const event = { key: 'ArrowUp', preventDefault: jest.fn() };
			
			row1.focus = jest.fn();
			
			handleKeyboardNavigation(event, row2, '[data-fp-seo-bulk-row]', null);
			
			expect(event.preventDefault).toHaveBeenCalled();
			expect(row1.focus).toHaveBeenCalled();
		});

		test('should not move focus if no next row exists', () => {
			setupTable();
			
			const row3 = document.getElementById('row3');
			const event = { key: 'ArrowDown', preventDefault: jest.fn() };
			
			handleKeyboardNavigation(event, row3, '[data-fp-seo-bulk-row]', null);
			
			expect(event.preventDefault).toHaveBeenCalled();
		});

		test('should toggle checkbox on Space key', () => {
			setupTable();
			
			const row1 = document.getElementById('row1');
			const checkbox = row1.querySelector('input');
			const event = { key: ' ', preventDefault: jest.fn() };
			const onToggle = jest.fn();
			
			checkbox.checked = false;
			
			handleKeyboardNavigation(event, row1, '[data-fp-seo-bulk-row]', onToggle);
			
			expect(event.preventDefault).toHaveBeenCalled();
			expect(checkbox.checked).toBe(true);
			expect(onToggle).toHaveBeenCalledWith('1', true);
		});

		test('should toggle checkbox on Spacebar key (legacy)', () => {
			setupTable();
			
			const row1 = document.getElementById('row1');
			const checkbox = row1.querySelector('input');
			const event = { key: 'Spacebar', preventDefault: jest.fn() };
			const onToggle = jest.fn();
			
			checkbox.checked = true;
			
			handleKeyboardNavigation(event, row1, '[data-fp-seo-bulk-row]', onToggle);
			
			expect(event.preventDefault).toHaveBeenCalled();
			expect(checkbox.checked).toBe(false);
			expect(onToggle).toHaveBeenCalledWith('1', false);
		});

		test('should navigate to link on Enter key', () => {
			const container = document.createElement('table');
			container.innerHTML = `
				<tbody>
					<tr data-fp-seo-bulk-row tabindex="-1" id="row1">
						<td><a href="/edit.php?post=123">Edit</a></td>
					</tr>
				</tbody>
			`;
			document.body.appendChild(container);
			
			const row1 = document.getElementById('row1');
			const event = { key: 'Enter', preventDefault: jest.fn() };
			
			// Mock window.location.assign
			const originalLocation = window.location;
			delete window.location;
			window.location = { assign: jest.fn() };
			
			handleKeyboardNavigation(event, row1, '[data-fp-seo-bulk-row]', null);
			
			expect(event.preventDefault).toHaveBeenCalled();
			expect(window.location.assign).toHaveBeenCalledWith('/edit.php?post=123');
			
			// Restore
			window.location = originalLocation;
		});

		test('should support legacy Down key', () => {
			setupTable();
			
			const row1 = document.getElementById('row1');
			const row2 = document.getElementById('row2');
			const event = { key: 'Down', preventDefault: jest.fn() };
			
			row2.focus = jest.fn();
			
			handleKeyboardNavigation(event, row1, '[data-fp-seo-bulk-row]', null);
			
			expect(row2.focus).toHaveBeenCalled();
		});

		test('should support legacy Up key', () => {
			setupTable();
			
			const row2 = document.getElementById('row2');
			const row1 = document.getElementById('row1');
			const event = { key: 'Up', preventDefault: jest.fn() };
			
			row1.focus = jest.fn();
			
			handleKeyboardNavigation(event, row2, '[data-fp-seo-bulk-row]', null);
			
			expect(row1.focus).toHaveBeenCalled();
		});
	});
});
