/**
 * Tests for Editor Metabox State Management
 * 
 * @package FP\SEO
 */

import { MetaboxState } from './state';

describe('MetaboxState', () => {
	
	describe('constructor', () => {
		
		test('should initialize with enabled true', () => {
			const state = new MetaboxState({ enabled: true, excluded: false });
			expect(state.isEnabled()).toBe(true);
		});

		test('should initialize with enabled false', () => {
			const state = new MetaboxState({ enabled: false, excluded: false });
			expect(state.isEnabled()).toBe(false);
		});

		test('should initialize with excluded true', () => {
			const state = new MetaboxState({ enabled: true, excluded: true });
			expect(state.isExcluded()).toBe(true);
		});

		test('should initialize with excluded false', () => {
			const state = new MetaboxState({ enabled: true, excluded: false });
			expect(state.isExcluded()).toBe(false);
		});

		test('should convert truthy values to boolean', () => {
			const state = new MetaboxState({ enabled: 1, excluded: 'yes' });
			expect(state.isEnabled()).toBe(true);
			expect(state.isExcluded()).toBe(true);
		});

		test('should convert falsy values to boolean', () => {
			const state = new MetaboxState({ enabled: 0, excluded: '' });
			expect(state.isEnabled()).toBe(false);
			expect(state.isExcluded()).toBe(false);
		});

		test('should initialize with null values', () => {
			const state = new MetaboxState({});
			expect(state.lastPayload).toBeNull();
			expect(state.timer).toBeNull();
			expect(state.busy).toBe(false);
		});
	});

	describe('setBusy / isBusy', () => {
		
		test('should set busy to true', () => {
			const state = new MetaboxState({ enabled: true });
			state.setBusy(true);
			expect(state.isBusy()).toBe(true);
		});

		test('should set busy to false', () => {
			const state = new MetaboxState({ enabled: true });
			state.setBusy(true);
			state.setBusy(false);
			expect(state.isBusy()).toBe(false);
		});

		test('should initialize with busy false', () => {
			const state = new MetaboxState({ enabled: true });
			expect(state.isBusy()).toBe(false);
		});
	});

	describe('setExcluded / isExcluded', () => {
		
		test('should set excluded to true', () => {
			const state = new MetaboxState({ enabled: true, excluded: false });
			state.setExcluded(true);
			expect(state.isExcluded()).toBe(true);
		});

		test('should set excluded to false', () => {
			const state = new MetaboxState({ enabled: true, excluded: true });
			state.setExcluded(false);
			expect(state.isExcluded()).toBe(false);
		});
	});

	describe('setLastPayload / getLastPayload', () => {
		
		test('should store and retrieve payload', () => {
			const state = new MetaboxState({ enabled: true });
			const payload = '{"title":"Test","content":"Content"}';
			
			state.setLastPayload(payload);
			expect(state.getLastPayload()).toBe(payload);
		});

		test('should update payload', () => {
			const state = new MetaboxState({ enabled: true });
			
			state.setLastPayload('payload1');
			expect(state.getLastPayload()).toBe('payload1');
			
			state.setLastPayload('payload2');
			expect(state.getLastPayload()).toBe('payload2');
		});

		test('should return null initially', () => {
			const state = new MetaboxState({ enabled: true });
			expect(state.getLastPayload()).toBeNull();
		});
	});

	describe('setTimer / getTimer / clearTimer', () => {
		
		beforeEach(() => {
			jest.useFakeTimers();
		});

		afterEach(() => {
			jest.useRealTimers();
		});

		test('should store and retrieve timer', () => {
			const state = new MetaboxState({ enabled: true });
			const timer = setTimeout(() => {}, 1000);
			
			state.setTimer(timer);
			expect(state.getTimer()).toBe(timer);
			
			clearTimeout(timer);
		});

		test('should clear timer', () => {
			const state = new MetaboxState({ enabled: true });
			const timer = setTimeout(() => {}, 1000);
			
			state.setTimer(timer);
			expect(state.getTimer()).toBe(timer);
			
			state.clearTimer();
			expect(state.getTimer()).toBeNull();
		});

		test('should not error when clearing null timer', () => {
			const state = new MetaboxState({ enabled: true });
			
			expect(() => {
				state.clearTimer();
			}).not.toThrow();
			
			expect(state.getTimer()).toBeNull();
		});

		test('should actually clear the timeout', () => {
			const state = new MetaboxState({ enabled: true });
			const callback = jest.fn();
			const timer = setTimeout(callback, 1000);
			
			state.setTimer(timer);
			state.clearTimer();
			
			jest.advanceTimersByTime(1500);
			
			expect(callback).not.toHaveBeenCalled();
		});

		test('should return null initially', () => {
			const state = new MetaboxState({ enabled: true });
			expect(state.getTimer()).toBeNull();
		});

		test('should allow setting timer multiple times', () => {
			const state = new MetaboxState({ enabled: true });
			
			const timer1 = setTimeout(() => {}, 1000);
			state.setTimer(timer1);
			
			const timer2 = setTimeout(() => {}, 2000);
			state.setTimer(timer2);
			
			expect(state.getTimer()).toBe(timer2);
			
			clearTimeout(timer1);
			clearTimeout(timer2);
		});
	});

	describe('integration scenarios', () => {
		
		test('should handle typical workflow', () => {
			const state = new MetaboxState({ enabled: true, excluded: false });
			
			// Initial state
			expect(state.isEnabled()).toBe(true);
			expect(state.isBusy()).toBe(false);
			expect(state.getLastPayload()).toBeNull();
			
			// Start analysis
			state.setBusy(true);
			const payload = '{"title":"Test"}';
			state.setLastPayload(payload);
			
			expect(state.isBusy()).toBe(true);
			expect(state.getLastPayload()).toBe(payload);
			
			// Complete analysis
			state.setBusy(false);
			
			expect(state.isBusy()).toBe(false);
			expect(state.getLastPayload()).toBe(payload); // Payload persists
		});

		test('should handle exclusion toggle', () => {
			const state = new MetaboxState({ enabled: true, excluded: false });
			
			expect(state.isExcluded()).toBe(false);
			
			// User excludes post
			state.setExcluded(true);
			expect(state.isExcluded()).toBe(true);
			
			// User re-enables post
			state.setExcluded(false);
			expect(state.isExcluded()).toBe(false);
		});

		test('should handle debounced requests', () => {
			jest.useFakeTimers();
			const state = new MetaboxState({ enabled: true });
			
			// First request
			const timer1 = setTimeout(() => {}, 500);
			state.setTimer(timer1);
			
			// User types more, cancel previous
			state.clearTimer();
			
			// New request
			const timer2 = setTimeout(() => {}, 500);
			state.setTimer(timer2);
			
			expect(state.getTimer()).toBe(timer2);
			
			clearTimeout(timer1);
			clearTimeout(timer2);
			jest.useRealTimers();
		});
	});
});
