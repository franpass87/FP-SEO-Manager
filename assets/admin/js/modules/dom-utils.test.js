/**
 * Tests for DOM Utilities Module
 * 
 * @package FP\SEO
 */

import { clearList, createElement, closest } from './dom-utils';

describe('DOM Utils', () => {
	
	beforeEach(() => {
		// Create a fresh DOM for each test
		document.body.innerHTML = `
			<div id="test-container">
				<ul id="test-list">
					<li>Item 1</li>
					<li>Item 2</li>
					<li>Item 3</li>
				</ul>
				<div class="parent">
					<div class="child">
						<span class="nested">Nested</span>
					</div>
				</div>
			</div>
		`;
	});

	describe('clearList', () => {
		
		test('removes all children from element', () => {
			const list = document.getElementById('test-list');
			
			clearList(list);
			
			expect(list.childNodes.length).toBe(0);
			expect(list.innerHTML).toBe('');
		});

		test('handles null element gracefully', () => {
			expect(() => {
				clearList(null);
			}).not.toThrow();
		});

		test('handles undefined element gracefully', () => {
			expect(() => {
				clearList(undefined);
			}).not.toThrow();
		});

		test('clears nested elements', () => {
			const container = document.getElementById('test-container');
			const initialChildren = container.childNodes.length;
			
			clearList(container);
			
			expect(container.childNodes.length).toBe(0);
			expect(initialChildren).toBeGreaterThan(0);
		});
	});

	describe('createElement', () => {
		
		test('creates basic element', () => {
			const div = createElement('div');
			
			expect(div).toBeInstanceOf(HTMLDivElement);
			expect(div.tagName).toBe('DIV');
		});

		test('creates element with class name', () => {
			const div = createElement('div', { className: 'test-class' });
			
			expect(div.className).toBe('test-class');
		});

		test('creates element with attributes', () => {
			const input = createElement('input', { 
				type: 'text',
				name: 'test-input',
				placeholder: 'Enter text'
			});
			
			expect(input.getAttribute('type')).toBe('text');
			expect(input.getAttribute('name')).toBe('test-input');
			expect(input.getAttribute('placeholder')).toBe('Enter text');
		});

		test('creates element with dataset', () => {
			const div = createElement('div', {
				dataset: {
					id: '123',
					score: '85',
					status: 'active'
				}
			});
			
			expect(div.dataset.id).toBe('123');
			expect(div.dataset.score).toBe('85');
			expect(div.dataset.status).toBe('active');
		});

		test('creates element with text content', () => {
			const p = createElement('p', {}, 'Hello World');
			
			expect(p.textContent).toBe('Hello World');
		});

		test('creates element with child element', () => {
			const child = createElement('span', {}, 'Child');
			const parent = createElement('div', {}, child);
			
			expect(parent.children.length).toBe(1);
			expect(parent.firstChild).toBe(child);
		});

		test('creates element with array of children', () => {
			const child1 = createElement('span', {}, 'First');
			const child2 = createElement('span', {}, 'Second');
			const parent = createElement('div', {}, [child1, child2]);
			
			expect(parent.children.length).toBe(2);
			expect(parent.children[0]).toBe(child1);
			expect(parent.children[1]).toBe(child2);
		});

		test('creates complex nested structure', () => {
			const button = createElement('button', {
				className: 'btn btn-primary',
				dataset: { action: 'submit' }
			}, 'Click Me');
			
			expect(button.className).toBe('btn btn-primary');
			expect(button.dataset.action).toBe('submit');
			expect(button.textContent).toBe('Click Me');
			expect(button.tagName).toBe('BUTTON');
		});

		test('handles empty content', () => {
			const div = createElement('div', {}, null);
			
			expect(div.innerHTML).toBe('');
			expect(div.childNodes.length).toBe(0);
		});
	});

	describe('closest', () => {
		
		test('finds closest matching parent', () => {
			const nested = document.querySelector('.nested');
			const parent = closest(nested, '.parent');
			
			expect(parent).toBeTruthy();
			expect(parent.className).toBe('parent');
		});

		test('finds closest matching element (self)', () => {
			const child = document.querySelector('.child');
			const result = closest(child, '.child');
			
			expect(result).toBe(child);
		});

		test('returns null when no match found', () => {
			const nested = document.querySelector('.nested');
			const result = closest(nested, '.non-existent');
			
			expect(result).toBeNull();
		});

		test('handles null element gracefully', () => {
			const result = closest(null, '.parent');
			
			expect(result).toBeNull();
		});

		test('handles undefined element gracefully', () => {
			const result = closest(undefined, '.parent');
			
			expect(result).toBeNull();
		});

		test('traverses multiple levels', () => {
			const nested = document.querySelector('.nested');
			const container = closest(nested, '#test-container');
			
			expect(container).toBeTruthy();
			expect(container.id).toBe('test-container');
		});

		test('uses element.closest if available', () => {
			const nested = document.querySelector('.nested');
			
			// Mock closest method
			const closestSpy = jest.spyOn(nested, 'closest');
			
			closest(nested, '.parent');
			
			expect(closestSpy).toHaveBeenCalledWith('.parent');
			
			closestSpy.mockRestore();
		});
	});

	describe('Integration scenarios', () => {
		
		test('creates list and clears it', () => {
			const list = createElement('ul', { id: 'dynamic-list' });
			
			// Add items
			for (let i = 1; i <= 5; i++) {
				const item = createElement('li', {}, `Item ${i}`);
				list.appendChild(item);
			}
			
			expect(list.children.length).toBe(5);
			
			// Clear list
			clearList(list);
			
			expect(list.children.length).toBe(0);
		});

		test('creates nested structure and finds parent', () => {
			const parent = createElement('div', { className: 'container' });
			const child = createElement('div', { className: 'content' });
			const grandchild = createElement('span', { className: 'text' }, 'Hello');
			
			child.appendChild(grandchild);
			parent.appendChild(child);
			
			document.body.appendChild(parent);
			
			const found = closest(grandchild, '.container');
			
			expect(found).toBe(parent);
			
			// Cleanup
			document.body.removeChild(parent);
		});
	});
});
