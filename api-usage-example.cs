using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Text.Json;
using System.Threading.Tasks;

class Program
{
    static async Task Main()
    {
        var mods1 = await GetSharedMods();
        Console.WriteLine("=== All Shared Mods ===");
        PrintMods(mods1);

        var mods2 = await GetSharedMods(search: "test");
        Console.WriteLine("=== Search for 'test' ===");
        PrintMods(mods2);

        var mods3 = await GetSharedMods(limit: 5);
        Console.WriteLine("=== Limit 5 mods ===");
        PrintMods(mods3);

        var mods4 = await GetSharedMods(includeCode: true, limit: 3);
        Console.WriteLine("=== Mods with code ===");
        foreach (var mod in mods4)
        {
            Console.WriteLine($"ID: {mod.Id}, Name: {mod.Name}, Owner: {mod.Owner}");
            Console.WriteLine($"Description: {mod.Description}");
            Console.WriteLine($"Code:\n{mod.Code}");
            Console.WriteLine("---");
        }
    }

    static async Task<List<Mod>> GetSharedMods(string search = "", int limit = 20, bool includeCode = false)
    {
        using var client = new HttpClient();
        string url = $"https://temp.3gv.org/xor/lua/api.php?limit={limit}";
        if (!string.IsNullOrEmpty(search)) url += $"&q={Uri.EscapeDataString(search)}";
        if (includeCode) url += "&code=1";

        var response = await client.GetStringAsync(url);
        var json = JsonSerializer.Deserialize<ApiResponse>(response);
        return json?.Results ?? new List<Mod>();
    }

    static void PrintMods(List<Mod> mods)
    {
        foreach (var mod in mods)
        {
            Console.WriteLine($"ID: {mod.Id}, Name: {mod.Name}, Owner: {mod.Owner}, Description: {mod.Description}");
        }
    }
}

class ApiResponse
{
    public int Count { get; set; }
    public List<Mod> Results { get; set; }
}

class Mod
{
    public string Id { get; set; }
    public string Name { get; set; }
    public string Owner { get; set; }
    public string Code { get; set; }
    public string Description { get; set; }
}
