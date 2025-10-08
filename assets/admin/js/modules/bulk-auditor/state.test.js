/**
 * Tests for Bulk Auditor State Module
 * 
 * @package FP\SEO
 */

import { BulkAuditorState } from './state';

describe('BulkAuditorState', () => {
	
	let state;

	beforeEach(() => {
		// Create fresh state instance before each test
		state = new BulkAuditorState();
	});

	describe('constructor', () => {
		
		test('initializes with default values', () => {
			expect(state.busy).toBe(false);
			expect(state.selectedIds).toBeInstanceOf(Set);
			expect(state.selectedIds.size).toBe(0);
		});
	});

	describe('setBusy / isBusy', () => {
		
		test('sets and gets busy state', () => {
			expect(state.isBusy()).toBe(false);

			state.setBusy(true);
			expect(state.isBusy()).toBe(true);

			state.setBusy(false);
			expect(state.isBusy()).toBe(false);
		});
	});

	describe('addSelection', () => {
		
		test('adds ID to selection', () => {
			state.addSelection(1);
			
			expect(state.getSelectedIds()).toContain('1');
		});

		test('converts numeric ID to string', () => {
			state.addSelection(123);
			
			expect(state.selectedIds.has('123')).toBe(true);
		});

		test('does not duplicate IDs', () => {
			state.addSelection(1);
			state.addSelection(1);
			
			expect(state.getSelectedIds()).toHaveLength(1);
		});

		test('handles string IDs', () => {
			state.addSelection('42');
			
			expect(state.getSelectedIds()).toContain('42');
		});
	});

	describe('removeSelection', () => {
		
		test('removes ID from selection', () => {
			state.addSelection(1);
			state.addSelection(2);
			
			state.removeSelection(1);
			
			expect(state.getSelectedIds()).not.toContain('1');
			expect(state.getSelectedIds()).toContain('2');
		});

		test('handles removing non-existent ID gracefully', () => {
			expect(() => {
				state.removeSelection(999);
			}).not.toThrow();
		});
	});

	describe('setSelection', () => {
		
		test('sets all IDs at once', () => {
			state.setSelection([1, 2, 3]);
			
			const selected = state.getSelectedIds();
			expect(selected).toHaveLength(3);
			expect(selected).toContain('1');
			expect(selected).toContain('2');
			expect(selected).toContain('3');
		});

		test('replaces previous selection', () => {
			state.setSelection([1, 2, 3]);
			state.setSelection([4, 5]);
			
			const selected = state.getSelectedIds();
			expect(selected).toHaveLength(2);
			expect(selected).not.toContain('1');
			expect(selected).toContain('4');
			expect(selected).toContain('5');
		});

		test('converts all IDs to strings', () => {
			state.setSelection([1, 2, 3]);
			
			state.selectedIds.forEach(id => {
				expect(typeof id).toBe('string');
			});
		});
	});

	describe('clearSelection', () => {
		
		test('removes all selected IDs', () => {
			state.setSelection([1, 2, 3]);
			
			state.clearSelection();
			
			expect(state.getSelectedIds()).toHaveLength(0);
		});
	});

	describe('getSelectedIds', () => {
		
		test('returns array of selected IDs', () => {
			state.setSelection([1, 2, 3]);
			
			const ids = state.getSelectedIds();
			
			expect(Array.isArray(ids)).toBe(true);
			expect(ids).toHaveLength(3);
		});

		test('returns empty array when no selection', () => {
			const ids = state.getSelectedIds();
			
			expect(ids).toEqual([]);
		});
	});

	describe('isSelected', () => {
		
		test('returns true for selected ID', () => {
			state.addSelection(1);
			
			expect(state.isSelected(1)).toBe(true);
		});

		test('returns false for non-selected ID', () => {
			state.addSelection(1);
			
			expect(state.isSelected(2)).toBe(false);
		});

		test('works with both string and numeric IDs', () => {
			state.addSelection('42');
			
			expect(state.isSelected(42)).toBe(true);
			expect(state.isSelected('42')).toBe(true);
		});
	});

	describe('Integration scenarios', () => {
		
		test('manages complete selection workflow', () => {
			// Start analysis
			state.setBusy(true);
			state.setSelection([1, 2, 3, 4, 5]);

			expect(state.isBusy()).toBe(true);
			expect(state.getSelectedIds()).toHaveLength(5);

			// Remove some items
			state.removeSelection(3);
			state.removeSelection(5);

			expect(state.getSelectedIds()).toHaveLength(3);

			// Complete analysis
			state.setBusy(false);
			state.clearSelection();

			expect(state.isBusy()).toBe(false);
			expect(state.getSelectedIds()).toHaveLength(0);
		});
	});
});
