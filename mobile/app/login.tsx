import { router } from "expo-router";
import { useState } from "react";
import { Pressable, StyleSheet, Text, TextInput, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { ApiError, login } from "../lib/api";
import { saveToken } from "../lib/session";

export default function LoginScreen() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit() {
    setBusy(true);
    setError(null);

    try {
      const { token } = await login(email.trim(), password);
      await saveToken(token);
      router.replace("/");
    } catch (e) {
      setError(
        e instanceof ApiError && e.status === 429
          ? "Too many attempts. Wait a minute and try again."
          : e instanceof ApiError
            ? "Those details don't match."
            : "Can't reach PaidUp. Check your connection.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <SafeAreaView style={styles.screen}>
      <Text style={styles.title}>Sign in to PaidUp</Text>
      <View style={styles.form}>
        <TextInput
          accessibilityLabel="Email"
          placeholder="Email"
          autoCapitalize="none"
          autoComplete="email"
          keyboardType="email-address"
          value={email}
          onChangeText={setEmail}
          style={styles.input}
        />
        <TextInput
          accessibilityLabel="Password"
          placeholder="Password"
          autoComplete="current-password"
          secureTextEntry
          value={password}
          onChangeText={setPassword}
          style={styles.input}
        />
        {error && (
          <Text accessibilityRole="alert" style={styles.error}>
            {error}
          </Text>
        )}
        <Pressable
          accessibilityRole="button"
          disabled={busy || !email || !password}
          onPress={submit}
          style={[styles.button, (busy || !email || !password) && styles.disabled]}
        >
          <Text style={styles.buttonText}>{busy ? "Signing in…" : "Sign in"}</Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, justifyContent: "center", padding: 24, backgroundColor: "#fff" },
  title: { fontSize: 24, fontWeight: "600" },
  form: { gap: 12, marginTop: 24 },
  input: { minHeight: 48, borderWidth: 1, borderColor: "#d4d4d8", borderRadius: 8, paddingHorizontal: 12, fontSize: 16 },
  error: { color: "#b91c1c" },
  button: { minHeight: 48, justifyContent: "center", alignItems: "center", borderRadius: 8, backgroundColor: "#18181b" },
  disabled: { opacity: 0.5 },
  buttonText: { color: "#fff", fontSize: 16, fontWeight: "600" },
});
