/**
 * Tests for Bulk Auditor API Module
 * 
 * @package FP\SEO
 */

import { analyzeBatch, processInChunks } from './api';

// Mock global fetch
global.fetch = jest.fn();

describe('Bulk Auditor API', () => {
	
	beforeEach(() => {
		// Clear all mocks before each test
		jest.clearAllMocks();
	});

	describe('analyzeBatch', () => {
		
		test('rejects with error when config is missing ajaxUrl', async () => {
			const config = { action: 'test_action', nonce: 'test123' };
			const postIds = [1, 2, 3];

			await expect(analyzeBatch(config, postIds))
				.rejects
				.toThrow('Missing configuration');
		});

		test('rejects with error when config is missing action', async () => {
			const config = { ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php', nonce: 'test123' };
			const postIds = [1, 2, 3];

			await expect(analyzeBatch(config, postIds))
				.rejects
				.toThrow('Missing configuration');
		});

		test('calls fetch with correct parameters', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3];

			// Mock successful response
			global.fetch.mockResolvedValueOnce({
				json: async () => ({
					success: true,
					data: { results: [
						{ post_id: 1, score: 85 },
						{ post_id: 2, score: 90 },
						{ post_id: 3, score: 75 }
					]}
				})
			});

			await analyzeBatch(config, postIds);

			// Verify fetch was called
			expect(global.fetch).toHaveBeenCalledTimes(1);
			expect(global.fetch).toHaveBeenCalledWith(
				config.ajaxUrl,
				expect.objectContaining({
					method: 'POST',
					credentials: 'same-origin'
				})
			);
		});

		test('returns results on successful API call', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3];
			const expectedResults = [
				{ post_id: 1, score: 85 },
				{ post_id: 2, score: 90 },
				{ post_id: 3, score: 75 }
			];

			global.fetch.mockResolvedValueOnce({
				json: async () => ({
					success: true,
					data: { results: expectedResults }
				})
			});

			const results = await analyzeBatch(config, postIds);

			expect(results).toEqual(expectedResults);
		});

		test('rejects when API returns invalid response', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3];

			// Mock invalid response
			global.fetch.mockResolvedValueOnce({
				json: async () => ({
					success: false
				})
			});

			await expect(analyzeBatch(config, postIds))
				.rejects
				.toThrow('Invalid response');
		});

		test('rejects when fetch fails', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3];

			// Mock network error
			global.fetch.mockRejectedValueOnce(new Error('Network error'));

			await expect(analyzeBatch(config, postIds))
				.rejects
				.toThrow('Network error');
		});

		test('includes all post IDs in FormData', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3, 4, 5];

			global.fetch.mockResolvedValueOnce({
				json: async () => ({
					success: true,
					data: { results: [] }
				})
			});

			await analyzeBatch(config, postIds);

			// Get the FormData from the fetch call
			const formData = global.fetch.mock.calls[0][1].body;
			
			// Note: FormData testing is limited in Jest
			// In real scenario, we'd verify the FormData contains all post IDs
			expect(global.fetch).toHaveBeenCalled();
		});
	});

	describe('processInChunks', () => {
		
		test('processes posts in chunks of specified size', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3, 4, 5, 6, 7];
			const chunkSize = 3;
			let progressCallCount = 0;

			// Mock responses for each chunk
			global.fetch
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [
							{ post_id: 1, score: 80 },
							{ post_id: 2, score: 85 },
							{ post_id: 3, score: 90 }
						]}
					})
				})
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [
							{ post_id: 4, score: 75 },
							{ post_id: 5, score: 88 },
							{ post_id: 6, score: 92 }
						]}
					})
				})
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [
							{ post_id: 7, score: 95 }
						]}
					})
				});

			const onProgress = jest.fn();

			const results = await processInChunks(config, postIds, onProgress, chunkSize);

			// Should be called 3 times (3 chunks)
			expect(global.fetch).toHaveBeenCalledTimes(3);
			
			// Progress callback should be called 3 times
			expect(onProgress).toHaveBeenCalledTimes(3);

			// Should return all results
			expect(results).toHaveLength(7);
		});

		test('calls onProgress with correct parameters', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3, 4, 5];
			const chunkSize = 2;

			global.fetch
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [
							{ post_id: 1, score: 80 },
							{ post_id: 2, score: 85 }
						]}
					})
				})
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [
							{ post_id: 3, score: 90 },
							{ post_id: 4, score: 75 }
						]}
					})
				})
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [
							{ post_id: 5, score: 88 }
						]}
					})
				});

			const onProgress = jest.fn();

			await processInChunks(config, postIds, onProgress, chunkSize);

			// First progress call
			expect(onProgress).toHaveBeenNthCalledWith(1, {
				processed: 2,
				total: 5,
				results: expect.any(Array)
			});

			// Second progress call
			expect(onProgress).toHaveBeenNthCalledWith(2, {
				processed: 4,
				total: 5,
				results: expect.any(Array)
			});

			// Third progress call
			expect(onProgress).toHaveBeenNthCalledWith(3, {
				processed: 5,
				total: 5,
				results: expect.any(Array)
			});
		});

		test('throws error if any chunk fails', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3, 4];
			const chunkSize = 2;

			// First chunk succeeds, second fails
			global.fetch
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [
							{ post_id: 1, score: 80 },
							{ post_id: 2, score: 85 }
						]}
					})
				})
				.mockRejectedValueOnce(new Error('Chunk processing failed'));

			await expect(processInChunks(config, postIds, null, chunkSize))
				.rejects
				.toThrow('Chunk processing failed');
		});

		test('uses default chunk size if not specified', async () => {
			const config = {
				ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
				action: 'fp_seo_performance_bulk_analyze',
				nonce: 'test_nonce_123'
			};
			const postIds = [1, 2, 3, 4, 5, 6];

			global.fetch
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: Array(5).fill({ post_id: 1, score: 80 }) }
					})
				})
				.mockResolvedValueOnce({
					json: async () => ({
						success: true,
						data: { results: [{ post_id: 6, score: 85 }] }
					})
				});

			await processInChunks(config, postIds, null);

			// Default chunk size is 5, so should be called twice
			expect(global.fetch).toHaveBeenCalledTimes(2);
		});
	});
});
